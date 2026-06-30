<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquipmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Helmet',          'name_ar' => 'خوذة',          'icon' => 'helmet',          'sort_order' => 1],
            ['name' => 'Jacket',          'name_ar' => 'جاكيت',         'icon' => 'jacket',          'sort_order' => 2],
            ['name' => 'Knee Protector',  'name_ar' => 'واقي الركبة',   'icon' => 'knee-protector',  'sort_order' => 3],
            ['name' => 'Back Protector',  'name_ar' => 'واقي الظهر',    'icon' => 'back-protector',  'sort_order' => 4],
            ['name' => 'Gloves',          'name_ar' => 'قفازات',        'icon' => 'gloves',          'sort_order' => 5],
            ['name' => 'Boots',           'name_ar' => 'أحذية',         'icon' => 'boots',           'sort_order' => 6],
        ];

        foreach ($types as $type) {
            DB::table('equipment_types')->updateOrInsert(
                ['name' => $type['name']],
                array_merge($type, ['is_active' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
