<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Http\Controllers\PlateGeneratorController;
use Illuminate\Http\Request;

class LicensePlate extends Model
{
    protected $fillable = [
        'listing_id',
        'country_id',
        'type_id',
        'color_id',
        'city_id',
        'plate_format_id',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PlateType::class, 'type_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(PlateColor::class, 'color_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(PlateFormat::class, 'plate_format_id');
    }

    public function plateFormat(): BelongsTo
    {
        return $this->belongsTo(PlateFormat::class, 'plate_format_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(LicensePlateValue::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(LicensePlateValue::class);
    }

    /**
     * Generate plate image automatically after creation/update
     */
    protected static function booted()
    {
        static::saved(function ($licensePlate) {
            // Generate plate image after save
            $licensePlate->generatePlateImage();
        });
    }

    /**
     * Generate the license plate image automatically
     */
    public function generatePlateImage()
    {
        try {
            $country = $this->country;
            $city = $this->city;

            if (!$country || !$city) {
                \Log::warning("Cannot generate plate: missing country or city", [
                    'license_plate_id' => $this->id,
                    'country_id' => $this->country_id,
                    'city_id' => $this->city_id
                ]);
                return;
            }

            // Determine country type
            $countryType = $this->determineCountryType($country, $city);

            // Get field values formatted for the request
            $fieldValues = $this->getFormattedFieldValues();

            // 🔍 LOG: Raw field values from database
            \Log::info("🎯 Raw field values from DB", [
                'license_plate_id' => $this->id,
                'field_values' => $fieldValues
            ]);

            // Prepare request data
            $requestData = [
                'country' => $countryType,
                'format' => 'png',
            ];

            // Add field values based on country type
            if ($countryType === 'ksa') {
                $requestData['top_left'] = $fieldValues['top_left'] ?? '';
                $requestData['top_right'] = $fieldValues['top_right'] ?? '';
                $requestData['bottom_left'] = $fieldValues['bottom_left'] ?? '';
                $requestData['bottom_right'] = $fieldValues['bottom_right'] ?? '';
            } else {
                // UAE and Dubai - Map field names correctly
                // Check all possible field name variations
                $requestData['category_number'] = $fieldValues['category_number']
                    ?? $fieldValues['top_center_digits']
                    ?? $fieldValues['top_center']
                    ?? '';

                $requestData['plate_number'] = $fieldValues['plate_number']
                    ?? $fieldValues['bottom_center_letter']
                    ?? $fieldValues['bottom_center']
                    ?? '';
            }

            // Add city names for dynamic display
            $requestData['city_name_ar'] = $city->name_ar ?? $city->name;
            $requestData['city_name_en'] = $city->name ?? '';

            // 🔍 LOG: Complete request data being sent
            \Log::info("📤 Complete request data to PlateGenerator", [
                'license_plate_id' => $this->id,
                'request_data' => $requestData
            ]);

            // Create a mock request
            $request = Request::create('/generate-plate', 'POST', $requestData);

            // Call the controller
            $controller = new PlateGeneratorController();
            $response = $controller->generatePlateInternal($request, $city);

            if ($response && isset($response['url'])) {
                // Save image to listing
                $this->listing->images()->create([
                    'image_url' => $response['url'],
                    'is_plate_image' => true
                ]);

                \Log::info("✅ Plate image generated successfully", [
                    'license_plate_id' => $this->id,
                    'country_type' => $countryType,
                    'image_url' => $response['url']
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("❌ Failed to generate plate image", [
                'license_plate_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Determine country type (ksa, dubai, uae)
     */
    private function determineCountryType($country, $city)
    {
        // Saudi Arabia
        if ($country->id == 1) {
            return 'ksa';
        }

        // UAE
        if ($country->id == 2) {
            // Abu Dhabi uses plate_uae template (fixe "أبو ظبي / ABU DHABI")
            if (stripos($city->name, 'Abu Dhabi') !== false || stripos($city->name_ar, 'أبو ظبي') !== false) {
                return 'uae';
            }

            // Toutes les autres villes UAE utilisent plate_dubai (nom dynamique)
            return 'dubai';
        }

        // Default
        return 'ksa';
    }

    /**
     * Get formatted field values
     */
    private function getFormattedFieldValues()
    {
        $values = [];

        // 🔍 LOG: Check what field values exist
        \Log::info("🔍 Checking fieldValues relation", [
            'license_plate_id' => $this->id,
            'fieldValues_count' => $this->fieldValues->count()
        ]);

        foreach ($this->fieldValues as $fieldValue) {
            // Use formatField() relation - handle both possible relation names
            $field = $fieldValue->formatField ?? $fieldValue->field ?? $fieldValue->plateFormatField;

            if ($field) {
                $fieldName = $field->field_name ?? $field->name;
                $fieldValueData = $fieldValue->field_value ?? $fieldValue->value;

                $values[$fieldName] = $fieldValueData;

                // 🔍 LOG: Each field mapping
                \Log::info("🔍 Field mapping", [
                    'field_name' => $fieldName,
                    'value' => $fieldValueData
                ]);
            } else {
                \Log::warning("⚠️ Field value without related field", [
                    'field_value_id' => $fieldValue->id,
                    'available_relations' => array_keys($fieldValue->getRelations())
                ]);
            }
        }

        return $values;
    }
}
