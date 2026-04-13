<?php

namespace Database\Seeders;

use App\Models\Assist\ExpertiseType;
use Illuminate\Database\Seeder;

class ExpertiseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'tire_repair', 'icon' => 'tire_repair'],
            ['name' => 'fuel',        'icon' => 'local_gas_station'],
            ['name' => 'mechanical',  'icon' => 'build'],
            ['name' => 'towing',      'icon' => 'car_crash'],
            ['name' => 'first_aid',   'icon' => 'medical_services'],
            ['name' => 'ev_support',  'icon' => 'electric_car'],
        ];

        foreach ($types as $type) {
            ExpertiseType::firstOrCreate(['name' => $type['name']], $type);
        }

        $this->command->info('Expertise types seeded: ' . implode(', ', array_column($types, 'name')));
    }
}
