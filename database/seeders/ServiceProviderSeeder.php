<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\City;
use App\Models\Country;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class ServiceProviderSeeder extends Seeder
{
    public function run()
    {
        // Vérifier que des villes et pays existent
        $riyadh = City::where('name', 'Riyadh')->orWhere('name_ar', 'الرياض')->first();
        $saudiArabia = Country::where('name', 'Saudi Arabia')->orWhere('code', 'SA')->first();

        if (!$riyadh || !$saudiArabia) {
            $this->command->warn('⚠️  Riyadh or Saudi Arabia not found. Please seed cities/countries first.');
            return;
        }

        // Obtenir le role par défaut (user)
        $userRole = Role::where('name', 'user')->first();
        if (!$userRole) {
            $this->command->warn('⚠️  User role not found. Using role_id = 2 by default.');
            $roleId = 2; // Fallback
        } else {
            $roleId = $userRole->id;
        }

        // Créer des utilisateurs providers
        $providers = [
            [
                'user' => [
                    'first_name' => 'Bike',
                    'last_name' => 'Club Provider',
                    'email' => 'provider1@dabapp.com',
                    'phone' => '+966501234567',
                    'password' => Hash::make('password123'),
                    'verified' => true,
                    'is_active' => true,
                    'role_id' => $roleId,
                ],
                'provider' => [
                    'business_name' => 'Bike Club',
                    'business_name_ar' => 'نادي الدراجات',
                    'description' => 'Professional motorcycle services and maintenance',
                    'description_ar' => 'خدمات وصيانة دراجات نارية احترافية',
                    'phone' => '+966501234567',
                    'email' => 'info@bikeclub.com',
                    'address' => 'Al-Naseem District, Riyadh',
                    'address_ar' => 'حي النسيم، الرياض',
                    'city_id' => $riyadh->id,
                    'country_id' => $saudiArabia->id,
                    'latitude' => 24.7136,
                    'longitude' => 46.6753,
                    'is_verified' => true,
                    'is_active' => true,
                    'rating_average' => 4.5,
                ],
            ],
            [
                'user' => [
                    'first_name' => 'Moto',
                    'last_name' => 'Club Provider',
                    'email' => 'provider2@dabapp.com',
                    'phone' => '+966502345678',
                    'password' => Hash::make('password123'),
                    'verified' => true,
                    'is_active' => true,
                    'role_id' => $roleId,
                ],
                'provider' => [
                    'business_name' => 'Moto Club',
                    'business_name_ar' => 'نادي الموتو',
                    'description' => 'Complete motorcycle service center',
                    'description_ar' => 'مركز خدمة دراجات نارية متكامل',
                    'phone' => '+966502345678',
                    'email' => 'info@motoclub.com',
                    'address' => 'Al-Malaz District, Riyadh',
                    'address_ar' => 'حي الملز، الرياض',
                    'city_id' => $riyadh->id,
                    'country_id' => $saudiArabia->id,
                    'latitude' => 24.6877,
                    'longitude' => 46.7219,
                    'is_verified' => true,
                    'is_active' => true,
                    'rating_average' => 4.8,
                ],
            ],
            [
                'user' => [
                    'first_name' => 'Fast',
                    'last_name' => 'Riders Provider',
                    'email' => 'provider3@dabapp.com',
                    'phone' => '+966503456789',
                    'password' => Hash::make('password123'),
                    'verified' => true,
                    'is_active' => true,
                    'role_id' => $roleId,
                ],
                'provider' => [
                    'business_name' => 'Fast Riders Moto Club',
                    'business_name_ar' => 'نادي الدراجات السريعة',
                    'description' => 'Premium motorcycle services and training',
                    'description_ar' => 'خدمات وتدريب دراجات نارية متميزة',
                    'phone' => '+966503456789',
                    'email' => 'info@fastriders.com',
                    'address' => 'Al-Suwaidi District, Riyadh',
                    'address_ar' => 'حي السويدي، الرياض',
                    'city_id' => $riyadh->id,
                    'country_id' => $saudiArabia->id,
                    'latitude' => 24.6408,
                    'longitude' => 46.7728,
                    'is_verified' => true,
                    'is_active' => true,
                    'rating_average' => 4.7,
                ],
            ],
        ];

        foreach ($providers as $providerData) {
            // Vérifier si l'utilisateur existe déjà
            $user = User::where('email', $providerData['user']['email'])->first();
            
            if (!$user) {
                // Créer l'utilisateur
                $user = User::create($providerData['user']);
            }

            // Vérifier si le provider existe déjà
            $existingProvider = ServiceProvider::where('user_id', $user->id)->first();
            
            if (!$existingProvider) {
                // Créer le provider
                $providerData['provider']['user_id'] = $user->id;
                ServiceProvider::create($providerData['provider']);
            }
        }

        $this->command->info('✅ Service Providers seeded successfully! ('. ServiceProvider::count() .' providers)');
    }
}