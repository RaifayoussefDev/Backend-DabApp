<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpecificRouteServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Assurer qu'il y a un pays (Saudi Arabia)
        $country = Country::firstOrCreate(
            ['code' => 'SA'],
            ['name' => 'Saudi Arabia', 'name_ar' => 'المملكة العربية السعودية', 'phone_code' => '966']
        );

        // 2. Créer les villes (Riyadh & Jeddah)
        $riyadh = City::firstOrCreate(
            ['name' => 'Riyadh'],
            ['name_ar' => 'الرياض', 'country_id' => $country->id]
        );

        $jeddah = City::firstOrCreate(
            ['name' => 'Jeddah'],
            ['name_ar' => 'جدة', 'country_id' => $country->id]
        );

        // 3. Assurer qu'il y a une catégorie "Towing"
        $category = ServiceCategory::firstOrCreate(
            ['slug' => 'towing-service'],
            [
                'name' => 'Towing Service',
                'name_ar' => 'خدمة سحب',
                'icon' => 'tow_truck',
                'color' => '#FF5733',
                'is_active' => true
            ]
        );

        // 4. Assurer qu'il y a un Provider
        $provider = ServiceProvider::first();
        if (!$provider) {
            $provider = ServiceProvider::create([
                'user_id' => 1, // Assumons user ID 1 si inexistant via factory
                'store_name' => 'Al-Faisal Towing',
                'store_name_ar' => 'الفيصل للسحب',
                'description' => 'Best towing in KSA',
                'description_ar' => 'أفضل خدمة سحب في المملكة',
                'license_number' => 'L-123456',
                'is_verified' => true,
                'is_active' => true,
                'city_id' => $riyadh->id,
            ]);
        }

        // 5. Créer le Service Spécifique (Riyadh -> Jeddah)
        $service = Service::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'name' => 'Towing Riyadh to Jeddah (Special)',
            'name_ar' => 'سحب من الرياض إلى جدة (عرض خاص)',
            'description' => 'Special rate for towing between Riyadh and Jeddah starting at 120 SAR base.',
            'description_ar' => 'سعر خاص للسحب بين الرياض وجدة يبدأ من 120 ريال كرسوم أساسية.',
            'price_type' => 'per_km',
            'price' => 0.20,      // 0.2 SAR par Km
            'base_price' => 120.00, // 120 SAR frais de base
            'origin_city_id' => $riyadh->id,
            'destination_city_id' => $jeddah->id,
            'currency' => 'SAR',
            'is_available' => true,
            'requires_booking' => true,
        ]);

        $this->command->info("Service created: {$service->name}");
        $this->command->info("Route: {$riyadh->name} -> {$jeddah->name}");
        $this->command->info("Pricing: {$service->base_price} SAR Base + {$service->price} SAR/km");
    }
}
