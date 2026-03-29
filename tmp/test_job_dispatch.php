<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Guide;
use App\Services\NotificationService;
use App\Jobs\NotifyPublishedGuideJob;
use Illuminate\Support\Facades\Queue;

Queue::fake();

$guide = Guide::first();

if (!$guide) {
    echo "No guide found to test with.\n";
    exit(1);
}

echo "Testing notification dispatch for guide ID: {$guide->id}...\n";

$service = app(NotificationService::class);
$service->notifyGuidePublished($guide);

Queue::assertPushed(NotifyPublishedGuideJob::class, function ($job) use ($guide) {
    return $job->guide->id === $guide->id;
});

echo "Success! NotifyPublishedGuideJob was dispatched correctly.\n";
