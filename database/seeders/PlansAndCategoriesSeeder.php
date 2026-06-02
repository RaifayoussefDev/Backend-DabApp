<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use App\Models\ServiceCategory;

class PlansAndCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedPlans();
    }

    private function seedCategories(): void
    {
        $categories = [
            [
                'name'         => 'Bike Transport',
                'name_ar'      => 'نقل الدراجات',
                'slug'         => 'bike-transport',
                'description'  => 'Secure carrier service for moving your motorcycle safely',
                'description_ar' => 'خدمة نقل آمنة لنقل دراجتك النارية بأمان',
                'icon'         => 'truck',
                'color'        => '#FF5722',
                'is_active'    => true,
                'order_position' => 1,
            ],
            [
                'name'         => 'Tow Service',
                'name_ar'      => 'خدمة السحب',
                'slug'         => 'tow-service',
                'description'  => 'Quick roadside assistance to get your bike moving again',
                'description_ar' => 'مساعدة سريعة على الطريق لتحريك دراجتك مرة أخرى',
                'icon'         => 'tow-truck',
                'color'        => '#F44336',
                'is_active'    => true,
                'order_position' => 2,
            ],
            [
                'name'         => 'Riding Instructor',
                'name_ar'      => 'مدرب قيادة',
                'slug'         => 'riding-instructor',
                'description'  => 'Book a one-on-one session with a certified riding coach',
                'description_ar' => 'احجز جلسة فردية مع مدرب قيادة معتمد',
                'icon'         => 'user-graduate',
                'color'        => '#2196F3',
                'is_active'    => true,
                'order_position' => 3,
            ],
            [
                'name'         => 'Bike Wash',
                'name_ar'      => 'غسيل الدراجات',
                'slug'         => 'bike-wash',
                'description'  => 'Mobile wash service that comes to you and cleans your motorcycle',
                'description_ar' => 'خدمة غسيل متنقلة تأتي إليك وتنظف دراجتك النارية',
                'icon'         => 'spray-can',
                'color'        => '#00BCD4',
                'is_active'    => true,
                'order_position' => 4,
            ],
            [
                'name'         => 'Maintenance Workshops',
                'name_ar'      => 'ورش الصيانة',
                'slug'         => 'maintenance-workshops',
                'description'  => 'Professional maintenance and repair services with expert technicians',
                'description_ar' => 'خدمات صيانة وإصلاح احترافية مع فنيين خبراء',
                'icon'         => 'wrench',
                'color'        => '#9C27B0',
                'is_active'    => true,
                'order_position' => 5,
            ],
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $this->command->info('✅ Service categories: ' . ServiceCategory::count() . ' total');
    }

    private function seedPlans(): void
    {
        $plans = [
            [
                'name'                  => 'Basic Plan',
                'name_ar'               => 'الخطة الأساسية',
                'slug'                  => 'basic-plan',
                'description'           => 'Perfect for getting started with essential features',
                'description_ar'        => 'مثالية للبدء مع الميزات الأساسية',
                'price_monthly'         => 19.00,
                'price_yearly'          => 190.00,
                'features'              => [
                    'Up to 5 services',
                    'Up to 50 bookings per month',
                    'Basic analytics',
                    'Email support',
                    'Standard listing visibility',
                ],
                'max_services'          => 5,
                'max_bookings_per_month'=> 50,
                'priority_support'      => false,
                'analytics_access'      => false,
                'is_featured'           => false,
                'is_active'             => true,
                'order_position'        => 1,
            ],
            [
                'name'                  => 'Business Plan',
                'name_ar'               => 'خطة الأعمال',
                'slug'                  => 'business-plan',
                'description'           => 'Flexible pricing that grows with you',
                'description_ar'        => 'تسعير مرن ينمو معك',
                'price_monthly'         => 29.00,
                'price_yearly'          => 290.00,
                'features'              => [
                    'Up to 15 services',
                    'Up to 200 bookings per month',
                    'Advanced analytics & insights',
                    'Priority email & chat support',
                    'Featured listing placement',
                    'Custom booking forms',
                ],
                'max_services'          => 15,
                'max_bookings_per_month'=> 200,
                'priority_support'      => true,
                'analytics_access'      => true,
                'is_featured'           => true,
                'is_active'             => true,
                'order_position'        => 2,
            ],
            [
                'name'                  => 'Enterprise Plan',
                'name_ar'               => 'الخطة المؤسسية',
                'slug'                  => 'enterprise-plan',
                'description'           => 'Advanced features for growing businesses',
                'description_ar'        => 'ميزات متقدمة للشركات المتنامية',
                'price_monthly'         => 39.00,
                'price_yearly'          => 390.00,
                'features'              => [
                    'Unlimited services',
                    'Unlimited bookings',
                    'Full analytics suite',
                    '24/7 Priority support',
                    'Premium listing placement',
                    'API access',
                    'Dedicated account manager',
                    'Custom integrations',
                ],
                'max_services'          => null,
                'max_bookings_per_month'=> null,
                'priority_support'      => true,
                'analytics_access'      => true,
                'is_featured'           => false,
                'is_active'             => true,
                'order_position'        => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('✅ Subscription plans: ' . SubscriptionPlan::count() . ' total');
    }
}
