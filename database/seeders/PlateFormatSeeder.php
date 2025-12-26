<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlateFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates plate formats:
     * - 1 format for KSA (all cities)
     * - 1 format for UAE Abu Dhabi
     * - 1 format for UAE Other Emirates (Dubai format)
     */
    public function run(): void
    {
        // Get country IDs
        $ksaCountryId = DB::table('countries')->where('name', 'SAUDI')->value('id');
        $uaeCountryId = DB::table('countries')->where('name', 'EMARAT')->value('id');

        // Get Abu Dhabi city ID
        $abuDhabiCityId = DB::table('cities')
            ->where('country_id', $uaeCountryId)
            ->where(function ($query) {
                $query->where('name', 'Abu Dhabi')
                      ->orWhere('name', 'ABU DHABI')
                      ->orWhere('name', 'abu dhabi');
            })
            ->value('id');

        if (!$ksaCountryId || !$uaeCountryId) {
            $this->command->error('Countries not found. Please run country seeder first.');
            return;
        }

        if (!$abuDhabiCityId) {
            $this->command->warn('Abu Dhabi city not found. Creating UAE Other Emirates format only.');
        }

        // ============================================
        // 1. KSA Format (for all cities)
        // ============================================
        $ksaFormatId = DB::table('plate_formats')->insertGetId([
            'name' => 'KSA Standard Format',
            'country_id' => $ksaCountryId,
            'city_id' => null, // null = applies to all cities in KSA
            'is_active' => true,
            'background_color' => '#FFFFFF',
            'text_color' => '#000000',
            'width_mm' => 520.0,
            'height_mm' => 110.0,
            'description' => 'Standard Saudi Arabia license plate format for all cities',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // KSA Fields: top_left, top_right, bottom_left, bottom_right
        DB::table('plate_format_fields')->insert([
            [
                'plate_format_id' => $ksaFormatId,
                'field_name' => 'top_left',
                'position' => 'top_left',
                'character_type' => 'alphanumeric',
                'writing_system' => 'arabic',
                'min_length' => 1,
                'max_length' => 10,
                'is_required' => true,
                'validation_pattern' => null,
                'font_size' => 14,
                'is_bold' => false,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plate_format_id' => $ksaFormatId,
                'field_name' => 'top_right',
                'position' => 'top_right',
                'character_type' => 'alphanumeric',
                'writing_system' => 'arabic',
                'min_length' => 1,
                'max_length' => 10,
                'is_required' => true,
                'validation_pattern' => null,
                'font_size' => 14,
                'is_bold' => false,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plate_format_id' => $ksaFormatId,
                'field_name' => 'bottom_left',
                'position' => 'bottom_left',
                'character_type' => 'alphanumeric',
                'writing_system' => 'arabic',
                'min_length' => 1,
                'max_length' => 10,
                'is_required' => true,
                'validation_pattern' => null,
                'font_size' => 14,
                'is_bold' => false,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plate_format_id' => $ksaFormatId,
                'field_name' => 'bottom_right',
                'position' => 'bottom_right',
                'character_type' => 'alphanumeric',
                'writing_system' => 'arabic',
                'min_length' => 1,
                'max_length' => 10,
                'is_required' => true,
                'validation_pattern' => null,
                'font_size' => 14,
                'is_bold' => false,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info("✅ Created KSA format (ID: {$ksaFormatId})");

        // ============================================
        // 2. UAE Abu Dhabi Format
        // ============================================
        if ($abuDhabiCityId) {
            $abuDhabiFormatId = DB::table('plate_formats')->insertGetId([
                'name' => 'UAE Abu Dhabi Format',
                'country_id' => $uaeCountryId,
                'city_id' => $abuDhabiCityId, // Specific to Abu Dhabi
                'is_active' => true,
                'background_color' => '#FFFFFF',
                'text_color' => '#000000',
                'width_mm' => 520.0,
                'height_mm' => 110.0,
                'description' => 'UAE license plate format for Abu Dhabi',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // UAE Abu Dhabi Fields: category_number, plate_number
            DB::table('plate_format_fields')->insert([
                [
                    'plate_format_id' => $abuDhabiFormatId,
                    'field_name' => 'category_number',
                    'position' => 'top_center',
                    'character_type' => 'numeric',
                    'writing_system' => 'latin',
                    'min_length' => 1,
                    'max_length' => 10,
                    'is_required' => true,
                    'validation_pattern' => null,
                    'font_size' => 16,
                    'is_bold' => true,
                    'display_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'plate_format_id' => $abuDhabiFormatId,
                    'field_name' => 'plate_number',
                    'position' => 'bottom_center',
                    'character_type' => 'alphanumeric',
                    'writing_system' => 'latin',
                    'min_length' => 1,
                    'max_length' => 10,
                    'is_required' => true,
                    'validation_pattern' => null,
                    'font_size' => 16,
                    'is_bold' => true,
                    'display_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $this->command->info("✅ Created UAE Abu Dhabi format (ID: {$abuDhabiFormatId})");
        }

        // ============================================
        // 3. UAE Other Emirates Format (Dubai format)
        // ============================================
        $uaeOtherFormatId = DB::table('plate_formats')->insertGetId([
            'name' => 'UAE Other Emirates Format',
            'country_id' => $uaeCountryId,
            'city_id' => null, // null = fallback for all other UAE cities
            'is_active' => true,
            'background_color' => '#FFFFFF',
            'text_color' => '#000000',
            'width_mm' => 520.0,
            'height_mm' => 110.0,
            'description' => 'UAE license plate format for Dubai and other Emirates',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // UAE Other Emirates Fields: category_number, plate_number (same as Abu Dhabi but different template)
        DB::table('plate_format_fields')->insert([
            [
                'plate_format_id' => $uaeOtherFormatId,
                'field_name' => 'category_number',
                'position' => 'top_center',
                'character_type' => 'numeric',
                'writing_system' => 'latin',
                'min_length' => 1,
                'max_length' => 10,
                'is_required' => true,
                'validation_pattern' => null,
                'font_size' => 16,
                'is_bold' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plate_format_id' => $uaeOtherFormatId,
                'field_name' => 'plate_number',
                'position' => 'bottom_center',
                'character_type' => 'alphanumeric',
                'writing_system' => 'latin',
                'min_length' => 1,
                'max_length' => 10,
                'is_required' => true,
                'validation_pattern' => null,
                'font_size' => 16,
                'is_bold' => true,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info("✅ Created UAE Other Emirates format (ID: {$uaeOtherFormatId})");
        $this->command->info("✅ Plate format seeder completed successfully!");
    }
}

