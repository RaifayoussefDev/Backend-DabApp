<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * DEV ONLY. Real logins go through an OTP (WhatsApp / email), which Postman cannot follow.
 * This prints a JWT for a demo account, or builds the whole Postman environment in one go.
 *
 *   php artisan marketplace:token buyer1
 *   php artisan marketplace:token admin --plain
 *   php artisan marketplace:token --postman="C:\Works\Projets\DabApp\marketplace-handoff\postman\DabApp-Marketplace-Local.postman_environment.json"
 */
class MarketplaceTokenCommand extends Command
{
    protected $signature = 'marketplace:token
        {who=buyer1 : buyer1, buyer2, vendor1, vendor2, vendor3, admin, or a full email}
        {--ttl=1440 : Token lifetime in minutes (default 24 h)}
        {--plain : Print only the token}
        {--postman= : Write a Postman environment file (all demo tokens + ids) to this path}';

    protected $description = 'DEV ONLY: JWT for a marketplace demo account (skips the OTP), or a ready-made Postman environment';

    private const DOMAIN = 'marketplace-demo.dabapp.test';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Refused: this command never runs in production.');

            return self::FAILURE;
        }

        JWTAuth::factory()->setTTL((int) $this->option('ttl'));

        if ($path = $this->option('postman')) {
            return $this->writePostmanEnvironment($path);
        }

        $user = $this->resolve((string) $this->argument('who'));
        if (! $user) {
            $this->error('User not found. Run: php artisan db:seed --class=MarketplaceDemoSeeder');

            return self::FAILURE;
        }

        $token = JWTAuth::fromUser($user);

        if ($this->option('plain')) {
            $this->line($token);

            return self::SUCCESS;
        }

        $this->info("{$user->email} (id {$user->id}, role_id {$user->role_id}), valid {$this->option('ttl')} min");
        $this->line($token);

        return self::SUCCESS;
    }

    private function resolve(string $who): ?User
    {
        if ($who === 'admin') {
            return User::where('role_id', 1)->where('is_active', true)->orderBy('id')->first();
        }

        $email = Str::contains($who, '@') ? $who : "{$who}@" . self::DOMAIN;

        return User::where('email', $email)->first();
    }

    private function writePostmanEnvironment(string $path): int
    {
        $accounts = ['admin' => 'admin', 'buyer1' => 'buyer1', 'buyer2' => 'buyer2', 'vendor1' => 'vendor1', 'vendor2' => 'vendor2', 'vendor3' => 'vendor3'];
        $values = [['key' => 'base_url', 'value' => 'http://localhost:8000', 'type' => 'default']];

        foreach ($accounts as $key => $who) {
            $user = $this->resolve($who);
            if (! $user) {
                $this->warn("skipped {$key}: not found (seed the demo data first)");

                continue;
            }

            $values[] = ['key' => "{$key}_token", 'value' => JWTAuth::fromUser($user), 'type' => 'secret'];
            $values[] = ['key' => "{$key}_id", 'value' => (string) $user->id, 'type' => 'default'];
        }

        $values[] = ['key' => 'demo_shop_slug', 'value' => 'demo-moto-parts-riyadh', 'type' => 'default'];
        $values[] = ['key' => 'pending_shop_slug', 'value' => 'demo-desert-moto-accessories', 'type' => 'default'];
        $values[] = ['key' => 'demo_category_slug', 'value' => 'tyres', 'type' => 'default'];

        $environment = [
            'id'                      => (string) Str::uuid(),
            'name'                    => 'DabApp Marketplace - Local',
            'values'                  => array_map(fn ($v) => $v + ['enabled' => true], $values),
            '_postman_variable_scope' => 'environment',
        ];

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Postman environment written: {$path}");
        $this->line("Tokens are valid {$this->option('ttl')} minutes. Import the file in Postman, select it, run the collection.");

        return self::SUCCESS;
    }
}
