<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use PromoCodesTableSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {


        $this->call([
            AdminMenuV2Seeder::class, // SPA-compatible menu tree (real React paths + key), replaces legacy AdminMenuSeeder
            AssistMenuSeeder::class,
            PaymentMenuSeeder::class,
            NotificationTemplateSeeder::class,
            ServiceCategorySeeder::class,
            TowTypeSeeder::class,
            ServiceProviderSeeder::class,
            ServiceSeeder::class,
            ExpertiseTypeSeeder::class,
            SpecialtySeeder::class,     // Dynamic trainer specialties (Coaching, Competition, Off-Road, Street, Custom)
            TrainerLevelSeeder::class,  // Trainer levels (Beginner, Intermediate, Advanced)
            EquipmentTypeSeeder::class, // Equipment catalog (Helmet, Jacket, Gloves, Boots, Knee/Back Protector)
        ]);
    }
}
