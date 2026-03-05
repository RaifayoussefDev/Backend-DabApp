<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    $poi = \App\Models\PointOfInterest::first();
    $review = \App\Models\PoiReview::create([
        'poi_id' => $poi->id,
        'user_id' => \App\Models\User::first()->id,
        'rating' => 5,
        'comment' => 'test'
    ]);

    $reviews = \App\Models\PoiReview::where('poi_id', $poi->id)->approved()->get();
    echo "Found " . $reviews->count() . " approved reviews.\n";
    echo "Review is_approved in memory: " . var_export($review->is_approved, true) . "\n";
    DB::rollBack();
} catch (\Exception $e) {
    echo $e->getMessage();
    DB::rollBack();
}
