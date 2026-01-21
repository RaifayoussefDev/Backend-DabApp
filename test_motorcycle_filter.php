<?php
try {
    $controller = new \App\Http\Controllers\MotorcycleFilterController();
    $brandsResponse = $controller->getBrands();
    $brands = $brandsResponse->getData(true)['data'];

    if (empty($brands)) {
        echo "No brands found. Cannot test.\n";
        exit;
    }

    $brandId = $brands[0]['id'];
    echo "Testing with Brand ID: $brandId ({$brands[0]['name']})\n";

    echo "\n--- getYearsByBrandMbl ---\n";
    $yearsResponse = $controller->getYearsByBrandMbl($brandId);
    $yearsData = $yearsResponse->getData(true);

    if ($yearsData['success']) {
        $years = $yearsData['data'];
        if (!empty($years)) {
            echo "First year entry:\n";
            print_r($years[0]);

            $yearValue = $years[0]['year'];

            echo "\n--- getModelsByBrandAndYear (Year: $yearValue) ---\n";
            $modelsResponse = $controller->getModelsByBrandAndYear($brandId, $yearValue);
            $modelsData = $modelsResponse->getData(true);

            if ($modelsData['success']) {
                $models = $modelsData['data'];
                if (!empty($models)) {
                    echo "First model entry:\n";
                    print_r($models[0]);
                } else {
                    echo "No models found for this year.\n";
                }
            } else {
                echo "Failed to get models.\n";
            }
        } else {
            echo "No years found for this brand.\n";
        }
    } else {
        echo "Failed to get years.\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
