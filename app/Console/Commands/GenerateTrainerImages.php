<?php

namespace App\Console\Commands;

use App\Models\Trainer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateTrainerImages extends Command
{
    protected $signature   = 'trainers:generate-images {--id= : Generate for a specific trainer ID}';
    protected $description = 'Generate AI images (photo + cover) for trainers using Pollinations.ai';

    private const PHOTO_W = 512;
    private const PHOTO_H = 512;
    private const COVER_W = 1280;
    private const COVER_H = 400;

    private array $specialtyPrompts = [
        'coaching'    => 'professional motorcycle riding coach, sports gear, helmet, track background',
        'competition' => 'professional motorcycle racer, racing suit, race track, action pose',
        'off-road'    => 'off-road motorcycle instructor, dirt bike gear, motocross track',
        'street'      => 'street motorcycle trainer, urban background, riding gear',
        'custom'      => 'custom motorcycle builder trainer, workshop, professional portrait',
    ];

    private array $coverPrompts = [
        'coaching'    => 'motorcycle training session on track, coach instructing student, wide angle',
        'competition' => 'motorcycle race track, high speed racing, action photography, wide shot',
        'off-road'    => 'motocross dirt track, off-road motorcycle training, wide landscape',
        'street'      => 'urban street motorcycle riding, city background, wide banner photo',
        'custom'      => 'custom motorcycle workshop, professional garage, wide angle photo',
    ];

    public function handle(): int
    {
        $trainers = $this->option('id')
            ? Trainer::where('id', $this->option('id'))->get()
            : Trainer::all();

        if ($trainers->isEmpty()) {
            $this->error('No trainers found.');
            return 1;
        }

        Storage::disk('public')->makeDirectory('trainers');

        foreach ($trainers as $trainer) {
            $this->info("\n  Processing: {$trainer->name} (ID: {$trainer->id})");

            $specialty    = $trainer->specialty ?? 'coaching';
            $seed         = $trainer->id * 42;
            $photoContext = $this->specialtyPrompts[$specialty] ?? $this->specialtyPrompts['coaching'];
            $coverContext = $this->coverPrompts[$specialty]     ?? $this->coverPrompts['coaching'];

            // Profile photo
            $photoPrompt = "portrait, {$photoContext}, photorealistic, professional photo, high quality";
            $photoPath   = $this->downloadImage(
                $photoPrompt, self::PHOTO_W, self::PHOTO_H, $seed,
                "trainers/photo_{$trainer->id}.jpg"
            );

            // Cover banner
            $coverPrompt = "{$coverContext}, cinematic, high quality, banner";
            $coverPath   = $this->downloadImage(
                $coverPrompt, self::COVER_W, self::COVER_H, $seed + 1,
                "trainers/cover_{$trainer->id}.jpg"
            );

            $updates = [];
            if ($photoPath) $updates['photo'] = $photoPath;
            if ($coverPath) $updates['cover'] = $coverPath;

            if ($updates) {
                $trainer->update($updates);
                $this->info("   Saved: " . implode(', ', array_keys($updates)));
            } else {
                $this->warn("   Failed to generate images for trainer {$trainer->id}");
            }
        }

        $this->info("\nDone!");
        return 0;
    }

    private function downloadImage(string $prompt, int $width, int $height, int $seed, string $dest): ?string // @phpstan-ignore-line
    {
        // Try services in order until one succeeds
        $urls = $this->buildUrls($prompt, $width, $height, $seed);

        $this->line("   Generating: {$dest}...");

        foreach ($urls as $label => $url) {
            try {
                $response = Http::timeout(60)->withoutVerifying()->get($url);

                if ($response->successful() && strlen($response->body()) > 5000) {
                    Storage::disk('public')->put($dest, $response->body());
                    $this->info("   Downloaded via {$label} (" . round(strlen($response->body()) / 1024) . " KB)");
                    return $dest;
                }
            } catch (\Exception $e) {
                $this->warn("   {$label} failed: " . $e->getMessage());
            }
        }

        $this->error("   All sources failed for: {$dest}");
        return null;
    }

    private function buildUrls(string $prompt, int $width, int $height, int $seed): array
    {
        // Extract keywords from prompt for themed queries
        $keywords = implode(',', array_slice(
            array_filter(explode(' ', preg_replace('/[^a-z\s]/', '', strtolower($prompt)))),
            0, 3
        ));

        return [
            // 1. Pollinations.ai (free tier, default model)
            'Pollinations' => sprintf(
                'https://image.pollinations.ai/prompt/%s?width=%d&height=%d&seed=%d&nologo=true',
                urlencode($prompt), $width, $height, $seed
            ),
            // 2. LoremFlickr (real photos by keyword)
            'LoremFlickr' => sprintf(
                'https://loremflickr.com/%d/%d/%s?random=%d&lock=%d',
                $width, $height, urlencode($keywords), $seed, $seed
            ),
            // 3. Picsum Photos (reliable placeholder, seeded)
            'Picsum' => sprintf(
                'https://picsum.photos/seed/%s/%d/%d',
                $seed, $width, $height
            ),
        ];
    }
}
