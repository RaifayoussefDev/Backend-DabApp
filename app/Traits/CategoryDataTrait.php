<?php

namespace App\Traits;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait CategoryDataTrait
{
    /**
     * Handle category specific data for motorcycles, spare parts, and license plates.
     * 
     * @param Listing $listing
     * @param Request $request
     * @return void
     */
    protected function handleCategorySpecificData(Listing $listing, Request $request)
    {
        // Determine category (1=motorcycle, 2=spare part, 3=license plate)
        $categoryId = $request->category_id ?? $listing->category_id;

        switch ($categoryId) {
            case 1: // Motorcycle
                $motorcycleData = array_filter($request->only([
                    'brand_id',
                    'model_id',
                    'year_id',
                    'engine',
                    'mileage',
                    'body_condition',
                    'modified',
                    'insurance',
                    'general_condition',
                    'vehicle_care',
                    'vehicle_care_other',
                    'transmission'
                ]));

                // Get type_id from motorcycle_models table
                if (!empty($motorcycleData['model_id'])) {
                    $model = \App\Models\MotorcycleModel::find($motorcycleData['model_id']);
                    if ($model && $model->type_id) {
                        $motorcycleData['type_id'] = $model->type_id;
                    }
                }

                if (!empty($motorcycleData)) {
                    $listing->motorcycle()->updateOrCreate(
                        ['listing_id' => $listing->id],
                        $motorcycleData
                    );
                }
                break;

            case 2: // Spare Part
                $sparePartData = array_filter($request->only([
                    'condition',
                    'bike_part_brand_id',
                    'bike_part_category_id',
                    'brand_other'
                ]));

                if (!empty($sparePartData)) {
                    $sparePart = $listing->sparePart()->updateOrCreate(
                        ['listing_id' => $listing->id],
                        $sparePartData
                    );

                    // Handle compatible motorcycles
                    if ($request->has('motorcycles') && is_array($request->motorcycles)) {
                        $sparePart->motorcycles()->delete();

                        foreach ($request->motorcycles as $moto) {
                            $sparePart->motorcycles()->create([
                                'brand_id' => $moto['brand_id'] ?? null,
                                'model_id' => $moto['model_id'] ?? null,
                                'year_id' => $moto['year_id'] ?? null,
                            ]);
                        }
                    }
                }
                break;

            case 3: // License Plate
                // Map request fields to database fields
                $licensePlateData = [];

                if ($request->has('plate_format_id')) {
                    $licensePlateData['plate_format_id'] = $request->plate_format_id;
                }

                if ($request->has('country_id_lp')) {
                    $licensePlateData['country_id'] = $request->country_id_lp;
                }

                if ($request->has('city_id_lp')) {
                    $licensePlateData['city_id'] = $request->city_id_lp;
                }

                // Remove null/empty values
                $licensePlateData = array_filter($licensePlateData);

                if (!empty($licensePlateData)) {
                    $licensePlate = $listing->licensePlate()->updateOrCreate(
                        ['listing_id' => $listing->id],
                        $licensePlateData
                    );

                    // Handle custom fields
                    if ($request->has('fields') && is_array($request->fields)) {
                        $licensePlate->fieldValues()->delete();

                        foreach ($request->fields as $field) {
                            if (!empty($field['field_id'])) {
                                $licensePlate->fieldValues()->create([
                                    'plate_format_field_id' => $field['field_id'],
                                    'field_value' => $field['value'] ?? '',
                                ]);
                            }
                        }

                        // Generate plate image after saving fields
                        try {
                            Log::info("Generating plate image for license_plate_id: " . $licensePlate->id);
                            $licensePlate->generatePlateImage();
                        } catch (\Exception $e) {
                            Log::error("Failed to generate plate image in handleCategorySpecificData", [
                                'license_plate_id' => $licensePlate->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
                break;
        }
    }
}
