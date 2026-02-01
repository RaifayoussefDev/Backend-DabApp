<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic Plan',
                'name_ar' => 'الخطة الأساسية',
                'slug' => 'basic-plan',
                'description' => 'Perfect for getting started with essential features',
                'description_ar' => 'مثالية للبدء مع الميزات الأساسية',
                'price_monthly' => 19.00,
                'price_yearly' => 190.00,
                'features' => [
                    'Up to 5 services',
                    'Up to 50 bookings per month',
                    'Basic analytics',
                    'Email support',
                    'Standard listing visibility',
                ],
                'max_services' => 5,
                'max_bookings_per_month' => 50,
                'priority_support' => false,
                'analytics_access' => false,
                'is_featured' => false,
                'is_active' => true,
                'order_position' => 1,
            ],
            [
                'name' => 'Business Plan',
                'name_ar' => 'خطة الأعمال',
                'slug' => 'business-plan',
                'description' => 'Flexible pricing that grows with you',
                'description_ar' => 'تسعير مرن ينمو معك',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'features' => [
                    'Up to 15 services',
                    'Up to 200 bookings per month',
                    'Advanced analytics & insights',
                    'Priority email & chat support',
                    'Featured listing placement',
                    'Custom booking forms',
                ],
                'max_services' => 15,
                'max_bookings_per_month' => 200,
                'priority_support' => true,
                'analytics_access' => true,
                'is_featured' => true,
                'is_active' => true,
                'order_position' => 2,
            ],
            [
                'name' => 'Enterprise Plan',
                'name_ar' => 'الخطة المؤسسية',
                'slug' => 'enterprise-plan',
                'description' => 'Advanced features for growing businesses',
                'description_ar' => 'ميزات متقدمة للشركات المتنامية',
                'price_monthly' => 39.00,
                'price_yearly' => 390.00,
                'features' => [
                    'Unlimited services',
                    'Unlimited bookings',
                    'Full analytics suite',
                    '24/7 Priority support',
                    'Premium listing placement',
                    'API access',
                    'Dedicated account manager',
                    'Custom integrations',
                ],
                'max_services' => null, // Unlimited
                'max_bookings_per_month' => null, // Unlimited
                'priority_support' => true,
                'analytics_access' => true,
                'is_featured' => false,
                'is_active' => true,
                'order_position' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }

        $this->command->info('Subscription plans seeded successfully!');
    }
}