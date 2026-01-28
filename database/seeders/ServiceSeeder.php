<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceCategory;

class ServiceSeeder extends Seeder
{
    public function run()
    {
        $providers = ServiceProvider::all();
        $categories = ServiceCategory::all();

        if ($providers->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('⚠️  Providers or Categories not found. Please seed them first.');
            return;
        }

        // Transport services
        $transportCategory = $categories->where('slug', 'bike-transport')->first();
        if ($transportCategory) {
            foreach ($providers->take(2) as $provider) {
                Service::create([
                    'provider_id' => $provider->id,
                    'category_id' => $transportCategory->id,
                    'name' => 'Bike Transport Service',
                    'name_ar' => 'خدمة نقل الدراجات',
                    'description' => 'Safe and secure motorcycle transportation service',
                    'description_ar' => 'خدمة نقل دراجات نارية آمنة ومضمونة',
                    'price' => 150.00,
                    'price_type' => 'per_km',
                    'currency' => 'SAR',
                    'duration_minutes' => 60,
                    'is_available' => true,
                    'requires_booking' => true,
                    'max_capacity' => 12,
                ]);
            }
        }

        // Riding Instructor services
        $instructorCategory = $categories->where('slug', 'riding-instructor')->first();
        if ($instructorCategory) {
            foreach ($providers as $provider) {
                Service::create([
                    'provider_id' => $provider->id,
                    'category_id' => $instructorCategory->id,
                    'name' => 'Riding Lessons',
                    'name_ar' => 'دروس القيادة',
                    'description' => 'Professional motorcycle riding lessons with certified instructors',
                    'description_ar' => 'دروس قيادة دراجات نارية احترافية مع مدربين معتمدين',
                    'price' => 200.00,
                    'price_type' => 'per_hour',
                    'currency' => 'SAR',
                    'duration_minutes' => 60,
                    'is_available' => true,
                    'requires_booking' => true,
                    'max_capacity' => 1,
                ]);
            }
        }

        // Bike Wash services
        $washCategory = $categories->where('slug', 'bike-wash')->first();
        if ($washCategory) {
            foreach ($providers as $provider) {
                Service::create([
                    'provider_id' => $provider->id,
                    'category_id' => $washCategory->id,
                    'name' => 'Bike Wash',
                    'name_ar' => 'غسيل الدراجة',
                    'description' => 'Mobile motorcycle washing service at your location',
                    'description_ar' => 'خدمة غسيل دراجات نارية متنقلة في موقعك',
                    'price' => 50.00,
                    'price_type' => 'fixed',
                    'currency' => 'SAR',
                    'duration_minutes' => 30,
                    'is_available' => true,
                    'requires_booking' => true,
                    'max_capacity' => 5,
                ]);
            }
        }

        // Maintenance Workshop services
        $maintenanceCategory = $categories->where('slug', 'maintenance-workshops')->first();
        if ($maintenanceCategory) {
            foreach ($providers as $provider) {
                Service::create([
                    'provider_id' => $provider->id,
                    'category_id' => $maintenanceCategory->id,
                    'name' => 'Consulting a technician',
                    'name_ar' => 'استشارة فني',
                    'description' => 'Chat with expert technicians for motorcycle maintenance advice',
                    'description_ar' => 'دردش مع فنيين خبراء للحصول على نصائح صيانة الدراجات النارية',
                    'price' => 20.00,
                    'price_type' => 'fixed',
                    'currency' => 'SAR',
                    'duration_minutes' => 30,
                    'is_available' => true,
                    'requires_booking' => true,
                    'max_capacity' => null,
                ]);
            }
        }

        $this->command->info('✅ Services seeded successfully!');
    }
}