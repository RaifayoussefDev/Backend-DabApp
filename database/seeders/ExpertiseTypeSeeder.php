<?php

namespace Database\Seeders;

use App\Models\Assist\ExpertiseType;
use Illuminate\Database\Seeder;

class ExpertiseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Production entries (created manually — patched with translations)
            ['name' => 'battery_jump', 'name_en' => 'Battery Jump Start', 'name_ar' => 'تشغيل البطارية',   'icon' => 'battery_charging_full'],
            ['name' => 'out_of_gas',   'name_en' => 'Out of Gas',         'name_ar' => 'نفاد الوقود',       'icon' => 'local_gas_station'],
            ['name' => 'tire',         'name_en' => 'Tire Repair',        'name_ar' => 'إصلاح الإطارات',   'icon' => 'tire_repair'],
            // Standard catalogue
            ['name' => 'tire_repair',  'name_en' => 'Tire Repair',        'name_ar' => 'إصلاح الإطارات',   'icon' => 'tire_repair'],
            ['name' => 'fuel',         'name_en' => 'Fuel Delivery',      'name_ar' => 'توصيل الوقود',      'icon' => 'local_gas_station'],
            ['name' => 'mechanical',   'name_en' => 'Mechanical Help',    'name_ar' => 'مساعدة ميكانيكية', 'icon' => 'build'],
            ['name' => 'towing',       'name_en' => 'Towing Service',     'name_ar' => 'خدمة السحب',        'icon' => 'car_crash'],
            ['name' => 'first_aid',    'name_en' => 'First Aid',          'name_ar' => 'الإسعافات الأولية', 'icon' => 'medical_services'],
            ['name' => 'ev_support',   'name_en' => 'EV Battery Support', 'name_ar' => 'دعم بطارية السيارة الكهربائية', 'icon' => 'electric_car'],
        ];

        foreach ($types as $type) {
            ExpertiseType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        $this->command->info('Expertise types seeded: ' . implode(', ', array_column($types, 'name')));
    }
}
