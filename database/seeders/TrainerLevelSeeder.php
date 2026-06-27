<?php

namespace Database\Seeders;

use App\Models\TrainerLevel;
use Illuminate\Database\Seeder;

class TrainerLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'name_en'                 => 'Beginner',
                'name_ar'                 => 'مبتدئ',
                'slug'                    => 'beginner',
                'description'             => 'For first-time riders. Basic bike control, safety and balance.',
                'required_certifications' => ['Basic riding license'],
                'sort_order'              => 1,
                'is_active'               => true,
            ],
            [
                'name_en'                 => 'Intermediate',
                'name_ar'                 => 'متوسط',
                'slug'                    => 'intermediate',
                'description'             => 'Multi-year rider with basic certifications. Circuit techniques and road safety.',
                'required_certifications' => ['Basic riding license', 'Safety course certificate'],
                'sort_order'              => 2,
                'is_active'               => true,
            ],
            [
                'name_en'                 => 'Advanced',
                'name_ar'                 => 'متقدم',
                'slug'                    => 'advanced',
                'description'             => 'Full certifications and extensive experience. High-performance circuit training.',
                'required_certifications' => ['FIM Level 2', 'MSF Certified', 'Competition license'],
                'sort_order'              => 3,
                'is_active'               => true,
            ],
        ];

        foreach ($levels as $data) {
            TrainerLevel::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $this->command->info('Trainer levels seeded: Beginner, Intermediate, Advanced.');
    }
}
