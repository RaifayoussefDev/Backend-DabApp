<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;

class ServiceCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Bike Transport',
                'name_ar' => 'نقل الدراجات',
                'slug' => 'bike-transport',
                'description' => 'Secure carrier service for moving your motorcycle safely',
                'description_ar' => 'خدمة نقل آمنة لنقل دراجتك النارية بأمان',
                'icon' => 'truck',
                'color' => '#FF5722',
                'is_active' => true,
                'order_position' => 1,
            ],
            [
                'name' => 'Tow Service',
                'name_ar' => 'خدمة السحب',
                'slug' => 'tow-service',
                'description' => 'Quick roadside assistance to get your bike moving again',
                'description_ar' => 'مساعدة سريعة على الطريق لتحريك دراجتك مرة أخرى',
                'icon' => 'tow-truck',
                'color' => '#F44336',
                'is_active' => true,
                'order_position' => 2,
            ],
            [
                'name' => 'Riding Instructor',
                'name_ar' => 'مدرب قيادة',
                'slug' => 'riding-instructor',
                'description' => 'Book a one-on-one session with a certified riding coach',
                'description_ar' => 'احجز جلسة فردية مع مدرب قيادة معتمد',
                'icon' => 'user-graduate',
                'color' => '#2196F3',
                'is_active' => true,
                'order_position' => 3,
            ],
            [
                'name' => 'Bike Wash',
                'name_ar' => 'غسيل الدراجات',
                'slug' => 'bike-wash',
                'description' => 'Mobile wash service that comes to you and cleans your motorcycle',
                'description_ar' => 'خدمة غسيل متنقلة تأتي إليك وتنظف دراجتك النارية',
                'icon' => 'spray-can',
                'color' => '#00BCD4',
                'is_active' => true,
                'order_position' => 4,
            ],
            [
                'name' => 'Maintenance Workshops',
                'name_ar' => 'ورش الصيانة',
                'slug' => 'maintenance-workshops',
                'description' => 'Professional maintenance and repair services with expert technicians',
                'description_ar' => 'خدمات صيانة وإصلاح احترافية مع فنيين خبراء',
                'icon' => 'wrench',
                'color' => '#9C27B0',
                'is_active' => true,
                'order_position' => 5,
            ],
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(
                ['slug' => $category['slug']], // Critère de recherche
                $category // Données à créer/mettre à jour
            );
        }

        $this->command->info('✅ Service Categories seeded successfully! ('. ServiceCategory::count() .' categories)');
    }
}