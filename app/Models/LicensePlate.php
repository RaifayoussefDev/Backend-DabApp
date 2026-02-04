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
        return $this->hasMany(
            LicensePlateValue::class,
            'license_plate_id',
            'id'
        );
    }

    public function values(): HasMany
    {
        return $this->fieldValues();
    }

    /**
     * ✅ NE PLUS générer automatiquement
     * La génération doit être appelée APRÈS la sauvegarde des fieldValues
     */
    protected static function booted()
    {
        // Vide - pas de génération automatique
    }

    /**
     * 🔥 Generate the license plate image
     */
    public function generatePlateImage()
    {
        try {
            \Log::info("🎯 ========== START PLATE GENERATION ==========", [
                'license_plate_id' => $this->id
            ]);

            $country = $this->country;
            $city = $this->city;

            if (!$country || !$city) {
                \Log::warning("❌ Cannot generate plate: missing country or city", [
                    'license_plate_id' => $this->id,
                    'country_id' => $this->country_id,
                    'city_id' => $this->city_id
                ]);
                return false;
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

            \Log::info("🎯 Raw field values from DB", [
                'license_plate_id' => $this->id,
                'country_type' => $countryType,
                'fieldValues_count' => count($fieldValues),
                'field_values' => $fieldValues
            ]);

            // Vérifier que les valeurs ne sont pas vides
            if (empty($fieldValues)) {
                \Log::warning("⚠️ Field values are empty after formatting, proceeding with blank template", [
                    'license_plate_id' => $this->id,
                    'raw_fieldValues' => $this->fieldValues->toArray()
                ]);
                // return false; // REMOVED: Allow generation even if empty
            }

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
                // UAE and Dubai
                \Log::info("🔍 DEBUG: All available field values for UAE/Dubai", [
                    'all_field_values' => $fieldValues
                ]);

                // Map category_number (can be from different field names)
                $requestData['category_number'] = $fieldValues['category_number']
                    ?? $fieldValues['top_center_digits']
                    ?? $fieldValues['top_center']
                    ?? $fieldValues['top_left']  // ← AJOUTÉ: fallback pour les anciennes données
                    ?? '';

                // Map plate_number (can be from different field names)
                $requestData['plate_number'] = $fieldValues['plate_number']
                    ?? $fieldValues['bottom_center_letter']
                    ?? $fieldValues['bottom_center']
                    ?? $fieldValues['top_right']  // ← AJOUTÉ: fallback pour les anciennes données
                    ?? '';

                \Log::info("🔍 DEBUG: Mapped values for UAE/Dubai", [
                    'category_number' => $requestData['category_number'],
                    'plate_number' => $requestData['plate_number'],
                    'source_fields_used' => [
                        'category_number_from' => $fieldValues['category_number'] ?? ($fieldValues['top_left'] ?? 'none'),
                        'plate_number_from' => $fieldValues['plate_number'] ?? ($fieldValues['top_right'] ?? 'none')
                    ]
                ]);
            }

            // Add city names
            $requestData['city_name_ar'] = $city->name_ar ?? $city->name;
            $requestData['city_name_en'] = $city->name ?? '';

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
                \Log::info("💾 Attempting to save image to database", [
                    'listing_id' => $this->listing_id,
                    'url' => $response['url']
                ]);

                // ✅ DELETE EXISTING OLD PLATE IMAGES
                \Illuminate\Support\Facades\DB::table('listing_images')
                    ->where('listing_id', $this->listing_id)
                    ->where('is_plate_image', true)
                    ->delete();

                // Save to listing_images
                $inserted = \Illuminate\Support\Facades\DB::table('listing_images')->insert([
                    'listing_id' => $this->listing_id,
                    'image_url' => $response['url'],
                    'is_plate_image' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($inserted) {
                    \Log::info("✅ SUCCESS: Image record created in listing_images table");
                } else {
                    \Log::error("❌ DATABASE ERROR: Insert failed for listing_id " . $this->listing_id);
                }

                \Log::info("✅ Plate image generated successfully", [
                    'license_plate_id' => $this->id,
                    'country_type' => $countryType,
                    'image_url' => $response['url']
                ]);

                return true;
            } else {
                \Log::error("❌ PlateGenerator returned null or invalid response");
                return false;
            }
        } catch (\Exception $e) {
            \Log::error("❌ Failed to generate plate image", [
                'license_plate_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } finally {
            \Log::info("🎯 ========== END PLATE GENERATION ==========");
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
     * Get formatted field values
     */
    private function getFormattedFieldValues()
    {
        $values = [];

        \Log::info("🔍 Checking fieldValues relation", [
            'license_plate_id' => $this->id,
            'fieldValues_count' => $this->fieldValues->count()
        ]);

        foreach ($this->fieldValues as $fieldValue) {
            $field = $fieldValue->formatField ?? $fieldValue->field ?? $fieldValue->plateFormatField;

            if ($field) {
                // Use variable_name as key, fallback to field_name
                $key = !empty($field->variable_name) ? $field->variable_name : $field->field_name;
                $fieldValueData = $fieldValue->field_value;

                $values[$key] = $fieldValueData;

                \Log::info("✅ Mapped field successfully", [
                    'field_id' => $field->id,
                    'key' => $key,
                    'field_name' => $field->field_name,
                    'variable_name' => $field->variable_name,
                    'position' => $field->position ?? 'N/A',
                    'value' => $fieldValueData,
                    'current_values_array' => $values
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
