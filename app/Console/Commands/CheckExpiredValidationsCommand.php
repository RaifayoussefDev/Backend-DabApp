<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckExpiredValidations;

class CheckExpiredValidationsCommand extends Command
{
    protected $signature = 'soom:check-expired-validations';
    protected $description = 'Check for expired sale validations and update status';

    public function handle()
    {
        $this->info('🔍 Checking for expired sale validations...');

        $expiredCount = (new CheckExpiredValidations())->handle();

        if ($expiredCount > 0) {
            $this->warn("⚠️  Processed {$expiredCount} expired validations.");
        } else {
            $this->info("✅ No expired validations found.");
        }

        return 0;
    }
}
