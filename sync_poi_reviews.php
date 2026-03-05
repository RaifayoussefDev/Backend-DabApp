<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pois = \App\Models\PointOfInterest::all();
foreach ($pois as $poi) {
    $reviews = \App\Models\PoiReview::where('poi_id', $poi->id)->approved()->get();
    $poi->update([
        'rating_average' => $reviews->avg('rating') ?? 0,
        'reviews_count' => $reviews->count(),
    ]);
}
echo "Synchronized " . $pois->count() . " POIs successfully.\n";
