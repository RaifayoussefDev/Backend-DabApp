<?php

namespace Database\Seeders;

use App\Models\ReportType;
use Illuminate\Database\Seeder;

class ReportTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'default', 'name_en' => 'Default', 'name_ar' => 'افتراضي'],
            ['code' => 'guide', 'name_en' => 'Guide', 'name_ar' => 'دليل'],
            ['code' => 'listing', 'name_en' => 'Listing', 'name_ar' => 'إعلان'],
            ['code' => 'event', 'name_en' => 'Event', 'name_ar' => 'حدث'],
            ['code' => 'comment', 'name_en' => 'Comment', 'name_ar' => 'تعليق'],
        ];

        foreach ($types as $type) {
            ReportType::firstOrCreate(
                ['code' => $type['code']],
                ['name_en' => $type['name_en'], 'name_ar' => $type['name_ar'], 'is_active' => true]
            );
        }
    }
}
