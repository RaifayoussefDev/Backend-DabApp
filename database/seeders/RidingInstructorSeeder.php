<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\InstructorLocation;
use App\Models\RidingInstructor;
use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

class RidingInstructorSeeder extends Seeder
{
    public function run(): void
    {
        $riyadh = City::where('name', 'Riyadh')->orWhere('name_ar', 'الرياض')->first();

        if (!$riyadh) {
            $this->command->warn('⚠️  Riyadh city not found. Please seed cities first.');
            return;
        }

        $providers = ServiceProvider::where('is_active', true)->where('is_verified', true)->get();

        if ($providers->isEmpty()) {
            $this->command->warn('⚠️  No active providers found. Run ServiceProviderSeeder first.');
            return;
        }

        $instructors = [
            [
                'instructor_name'    => 'Khalid Al-Mansouri',
                'instructor_name_ar' => 'خالد المنصوري',
                'bio'                => 'Professional motorcycle instructor with 12 years of experience. Specializes in off-road and adventure riding techniques.',
                'bio_ar'             => 'مدرب دراجات نارية محترف بخبرة 12 عامًا. متخصص في تقنيات القيادة على الطرق الوعرة والرياضية.',
                'photo'              => null,
                'certifications'     => ['FIM Level 2', 'Saudi Motorsport Federation', 'First Aid Certified'],
                'experience_years'   => 12,
                'rating_average'     => 4.9,
                'total_sessions'     => 340,
                'is_available'       => true,
                'provider_index'     => 0,
                'locations' => [
                    [
                        'location_name'    => 'Al-Naseem Training Circuit',
                        'location_name_ar' => 'حلبة النسيم للتدريب',
                        'latitude'         => 24.7250,
                        'longitude'        => 46.6900,
                        'is_available'     => true,
                    ],
                    [
                        'location_name'    => 'King Fahd Road Track',
                        'location_name_ar' => 'مضمار طريق الملك فهد',
                        'latitude'         => 24.7400,
                        'longitude'        => 46.6600,
                        'is_available'     => true,
                    ],
                ],
            ],
            [
                'instructor_name'    => 'Mohammed Al-Zahrani',
                'instructor_name_ar' => 'محمد الزهراني',
                'bio'                => 'Former national championship racer turned instructor. Expert in circuit racing and high-speed control.',
                'bio_ar'             => 'سباق بطولة وطنية سابق تحول إلى مدرب. خبير في سباقات الحلبة والتحكم بالسرعات العالية.',
                'photo'              => null,
                'certifications'     => ['National Racing License', 'FIM Level 3', 'Advanced Safety Instructor'],
                'experience_years'   => 8,
                'rating_average'     => 4.7,
                'total_sessions'     => 210,
                'is_available'       => true,
                'provider_index'     => 1,
                'locations' => [
                    [
                        'location_name'    => 'Moto Club Main Track',
                        'location_name_ar' => 'حلبة نادي الموتو الرئيسية',
                        'latitude'         => 24.6877,
                        'longitude'        => 46.7219,
                        'is_available'     => true,
                    ],
                ],
            ],
            [
                'instructor_name'    => 'Omar Al-Ghamdi',
                'instructor_name_ar' => 'عمر الغامدي',
                'bio'                => 'Beginner-friendly instructor focused on safety-first riding. Over 400 students trained to date.',
                'bio_ar'             => 'مدرب ودود للمبتدئين يركز على قيادة السلامة أولاً. درّب أكثر من 400 طالب حتى الآن.',
                'photo'              => null,
                'certifications'     => ['Basic Riding Instructor', 'Road Safety Certificate', 'First Aid Certified'],
                'experience_years'   => 6,
                'rating_average'     => 4.8,
                'total_sessions'     => 420,
                'is_available'       => true,
                'provider_index'     => 2,
                'locations' => [
                    [
                        'location_name'    => 'Fast Riders Academy — East',
                        'location_name_ar' => 'أكاديمية فاست رايدرز — الشرق',
                        'latitude'         => 24.6450,
                        'longitude'        => 46.7800,
                        'is_available'     => true,
                    ],
                    [
                        'location_name'    => 'Fast Riders Academy — West',
                        'location_name_ar' => 'أكاديمية فاست رايدرز — الغرب',
                        'latitude'         => 24.6380,
                        'longitude'        => 46.7650,
                        'is_available'     => false,
                    ],
                ],
            ],
            [
                'instructor_name'    => 'Faisal Al-Otaibi',
                'instructor_name_ar' => 'فيصل العتيبي',
                'bio'                => 'Dual-sport and touring specialist. Expert in long-distance riding techniques and navigation.',
                'bio_ar'             => 'متخصص في الدراجات المزدوجة والجولات الطويلة. خبير في تقنيات القيادة لمسافات طويلة والملاحة.',
                'photo'              => null,
                'certifications'     => ['Touring Instructor Level 2', 'Desert Navigation Certificate'],
                'experience_years'   => 10,
                'rating_average'     => 4.6,
                'total_sessions'     => 185,
                'is_available'       => true,
                'provider_index'     => 0,
                'locations' => [
                    [
                        'location_name'    => 'Al-Naseem Open Training Area',
                        'location_name_ar' => 'منطقة النسيم المفتوحة للتدريب',
                        'latitude'         => 24.7300,
                        'longitude'        => 46.6950,
                        'is_available'     => true,
                    ],
                ],
            ],
        ];

        foreach ($instructors as $data) {
            $provider = $providers->get($data['provider_index']) ?? $providers->first();

            $existing = RidingInstructor::where('instructor_name', $data['instructor_name'])
                ->where('provider_id', $provider->id)
                ->first();

            if ($existing) {
                continue;
            }

            $instructor = RidingInstructor::create([
                'provider_id'      => $provider->id,
                'instructor_name'  => $data['instructor_name'],
                'instructor_name_ar' => $data['instructor_name_ar'],
                'bio'              => $data['bio'],
                'bio_ar'           => $data['bio_ar'],
                'photo'            => $data['photo'],
                'certifications'   => $data['certifications'],
                'experience_years' => $data['experience_years'],
                'rating_average'   => $data['rating_average'],
                'total_sessions'   => $data['total_sessions'],
                'is_available'     => $data['is_available'],
            ]);

            foreach ($data['locations'] as $loc) {
                InstructorLocation::create([
                    'instructor_id'    => $instructor->id,
                    'location_name'    => $loc['location_name'],
                    'location_name_ar' => $loc['location_name_ar'],
                    'city_id'          => $riyadh->id,
                    'latitude'         => $loc['latitude'],
                    'longitude'        => $loc['longitude'],
                    'is_available'     => $loc['is_available'],
                ]);
            }
        }

        $count = RidingInstructor::count();
        $this->command->info("✅ Riding Instructors seeded successfully! ({$count} instructors)");
    }
}
