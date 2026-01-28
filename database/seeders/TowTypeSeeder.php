<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TowType;

class TowTypeSeeder extends Seeder
{
    public function run()
    {
        $towTypes = [
            [
                'name' => 'Tow Type 1',
                'name_ar' => 'نوع السحب 1',
                'description' => 'Standard towing service for small to medium motorcycles',
                'description_ar' => 'خدمة سحب قياسية للدراجات النارية الصغيرة والمتوسطة',
                'icon' => 'truck-pickup',
                'image' => null,
                'base_price' => 50.00,
                'price_per_km' => 5.00,
                'is_active' => true,
                'order_position' => 1,
            ],
            [
                'name' => 'Tow Type 2',
                'name_ar' => 'نوع السحب 2',
                'description' => 'Heavy-duty towing for large motorcycles and touring bikes',
                'description_ar' => 'سحب للخدمة الشاقة للدراجات النارية الكبيرة ودراجات الرحلات',
                'icon' => 'truck',
                'image' => null,
                'base_price' => 75.00,
                'price_per_km' => 7.00,
                'is_active' => true,
                'order_position' => 2,
            ],
            [
                'name' => 'Tow Type 3',
                'name_ar' => 'نوع السحب 3',
                'description' => 'Premium enclosed towing for high-value motorcycles',
                'description_ar' => 'سحب مغلق ممتاز للدراجات النارية عالية القيمة',
                'icon' => 'trailer',
                'image' => null,
                'base_price' => 100.00,
                'price_per_km' => 10.00,
                'is_active' => true,
                'order_position' => 3,
            ],
            [
                'name' => 'Tow Type 4',
                'name_ar' => 'نوع السحب 4',
                'description' => 'Express 24/7 emergency towing service',
                'description_ar' => 'خدمة سحب طوارئ سريعة على مدار الساعة',
                'icon' => 'ambulance',
                'image' => null,
                'base_price' => 120.00,
                'price_per_km' => 12.00,
                'is_active' => true,
                'order_position' => 4,
            ],
        ];

        foreach ($towTypes as $towType) {
            TowType::updateOrCreate(
                ['name' => $towType['name'], 'order_position' => $towType['order_position']], // Critère
                $towType // Données
            );
        }

        $this->command->info('✅ Tow Types seeded successfully! ('. TowType::count() .' types)');
    }
}