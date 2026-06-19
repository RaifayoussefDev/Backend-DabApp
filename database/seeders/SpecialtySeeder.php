<?php

namespace Database\Seeders;

use App\Models\Specialty;
use App\Models\Trainer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['libelle_en' => 'Coaching',     'libelle_ar' => 'تدريب',           'slug' => 'coaching',     'sort_order' => 0],
            ['libelle_en' => 'Competition',  'libelle_ar' => 'منافسة',          'slug' => 'competition',  'sort_order' => 1],
            ['libelle_en' => 'Off-Road',     'libelle_ar' => 'الطرق الوعرة',    'slug' => 'off-road',     'sort_order' => 2],
            ['libelle_en' => 'Street',       'libelle_ar' => 'قيادة الشوارع',   'slug' => 'street',       'sort_order' => 3],
            ['libelle_en' => 'Custom',       'libelle_ar' => 'مخصص',            'slug' => 'custom',       'sort_order' => 4],
        ];

        $slugToId = [];
        foreach ($specialties as $data) {
            $specialty = Specialty::firstOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['is_active' => true])
            );
            $slugToId[$data['slug']] = $specialty->id;
        }

        // Migrate existing trainers' legacy specialty enum → pivot table
        Trainer::whereNotNull('specialty')->chunk(100, function ($trainers) use ($slugToId) {
            foreach ($trainers as $trainer) {
                $slug = $trainer->specialty;
                if (isset($slugToId[$slug])) {
                    $trainer->specialties()->syncWithoutDetaching([$slugToId[$slug]]);
                }
            }
        });

        $this->command->info('Specialties seeded and migrated from trainer.specialty column.');
    }
}
