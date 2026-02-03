<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

// Manually test the query
try {
    echo "Testing Listing query...\n";
    $query = \App\Models\Listing::with([
        'seller',
        'images',
        'category',
        'city',
        'country',
        'country.currencyExchangeRate',
        'motorcycle.brand',
        'motorcycle.model',
        'motorcycle.year',
        'sparePart.bikePartBrand',
        'sparePart.bikePartCategory',
        'sparePart.motorcycleAssociations.brand',
        'sparePart.motorcycleAssociations.model',
        'sparePart.motorcycleAssociations.year',
        'licensePlate.format',
        'licensePlate.city',
        'licensePlate.fieldValues.formatField'
    ])
        ->orderBy('created_at', 'desc')
        ->limit(1)
        ->get();

    echo "Query executed successfully.\n";
    echo "Loaded " . $query->count() . " listings.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
