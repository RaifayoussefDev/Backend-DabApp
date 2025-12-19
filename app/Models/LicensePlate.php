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

    /**
     * ✅ CORRECTION CRITIQUE : Spécifier explicitement les clés
     *
     * La table license_plates a comme PK : id
     * La table license_plate_values a comme FK : license_plate_id
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(
            LicensePlateValue::class,
            'license_plate_id',  // ← Foreign key dans license_plate_values
            'id'                 // ← Local key dans license_plates (PK)
        );
    }

    /**
     * Alias pour la relation (si utilisé ailleurs dans le code)
     */
    public function values(): HasMany
    {
        return $this->fieldValues();
    }

    /**
     * Boot method - NO AUTO GENERATION
     * La génération de l'image sera appelée MANUELLEMENT après que les fieldValues soient sauvegardés
     */
    protected static function booted()
    {
        // ✅ NE PLUS générer automatiquement ici
        // La génération sera appelée depuis handleCategorySpecificData() APRÈS la sauvegarde des fieldValues

        static::saved(function ($licensePlate) {
            \Log::info("🔥 LicensePlate saved event", [
                'license_plate_id' => $licensePlate->id,
                'fieldValues_count_in_event' => $licensePlate->fieldValues()->count()
            ]);
        });
    }

    /**
     * Generate the license plate image automatically
     */
    public function generatePlateImage()
    {
        try {
            \Log::info("🎯 ========== START PLATE GENERATION ==========", [
                'license_plate_id' => $this->id,
                'country_id' => $this->country_id,
                'city_id' => $this->city_id,
                'plate_format_id' => $this->plate_format_id,
            ]);

            // ✅ FORCE RELOAD avec la clé corrigée
            $this->load(['fieldValues' => function($query) {
                $query->with('formatField');
            }]);

            \Log::info("🔍 After explicit load", [
                'fieldValues_count' => $this->fieldValues->count(),
                'fieldValues_loaded' => $this->relationLoaded('fieldValues')
            ]);

            $country = $this->country;
            $city = $this->city;

            if (!$country || !$city) {
                \Log::warning("❌ Cannot generate plate: missing country or city", [
                    'license_plate_id' => $this->id,
                    'country_id' => $this->country_id,
                    'city_id' => $this->city_id
                ]);
                return;
            }

            // Determine country type
            $countryType = $this->determineCountryType($country, $city);

            \Log::info("🌍 Country type determined", [
                'country_type' => $countryType,
                'country_id' => $country->id,
                'city_name' => $city->name
            ]);

            // Get field values formatted for the request
            $fieldValues = $this->getFormattedFieldValues();

            // 🔍 LOG: Raw field values from database
            \Log::info("🎯 Formatted field values", [
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
            } else {
                \Log::error("❌ PlateGenerator returned null or invalid response");
            }

            \Log::info("🎯 ========== END PLATE GENERATION ==========");

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
            // Abu Dhabi uses plate_uae template
            if (stripos($city->name, 'Abu Dhabi') !== false || stripos($city->name_ar, 'أبو ظبي') !== false) {
                return 'uae';
            }

            // Toutes les autres villes UAE utilisent plate_dubai
            return 'dubai';
        }

        // Default
        return 'ksa';
    }

    /**
     * Get formatted field values with detailed logging
     */
    private function getFormattedFieldValues()
    {
        $values = [];

        \Log::info("🔍 START getFormattedFieldValues", [
            'license_plate_id' => $this->id,
            'fieldValues_loaded' => $this->relationLoaded('fieldValues'),
            'fieldValues_count' => $this->fieldValues->count()
        ]);

        foreach ($this->fieldValues as $index => $fieldValue) {
            \Log::info("🔍 Processing fieldValue #{$index}", [
                'field_value_id' => $fieldValue->id,
                'plate_format_field_id' => $fieldValue->plate_format_field_id,
                'field_value' => $fieldValue->field_value,
            ]);

            // Use formatField() relation - handle both possible relation names
            $field = $fieldValue->formatField ?? $fieldValue->field ?? $fieldValue->plateFormatField;

            if ($field) {
                $fieldName = $field->field_name ?? $field->name;
                $fieldValueData = $fieldValue->field_value ?? $fieldValue->value;

                $values[$fieldName] = $fieldValueData;

                \Log::info("✅ Mapped field successfully", [
                    'field_id' => $field->id,
                    'field_name' => $fieldName,
                    'position' => $field->position ?? 'N/A',
                    'value' => $fieldValueData,
                    'current_values_array' => $values
                ]);
            } else {
                \Log::error("❌ NO FIELD FOUND for fieldValue", [
                    'field_value_id' => $fieldValue->id,
                    'relations_loaded' => array_keys($fieldValue->getRelations()),
                    'attributes' => $fieldValue->getAttributes()
                ]);
            }
        }

        \Log::info("🎯 FINAL formatted values", [
            'values' => $values,
            'count' => count($values)
        ]);

        return $values;
    }
}
