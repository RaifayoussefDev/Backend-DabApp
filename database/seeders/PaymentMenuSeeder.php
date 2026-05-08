<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminMenu;

class PaymentMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (AdminMenu::where('name', 'payment-history')->exists()) {
            $this->command->warn('Payment History menu already exists — skipping.');
            return;
        }

        $parent = AdminMenu::create([
            'name'           => 'payment-history',
            'title'          => 'Payment History',
            'translate'      => 'سجل المدفوعات',
            'icon'           => 'payments',
            'path'           => '/payments',
            'type'           => 'collapse',
            'permission'     => 'payments.view',
            'roles'          => ['admin', 'Manager'],
            'order'          => 21,
            'is_main_parent' => true,
            'is_active'      => true,
            'hidden'         => false,
            'disabled'       => false,
            'external'       => false,
            'target'         => false,
            'exact_match'    => false,
            'breadcrumbs'    => true,
        ]);

        $children = [
            [
                'name'      => 'payment-listing-payments',
                'title'     => 'Listing Payments',
                'translate' => 'مدفوعات الإعلانات',
                'icon'      => 'listing-payments',
                'path'      => '/payments',
                'order'     => 1,
            ],
            [
                'name'      => 'payment-subscription-transactions',
                'title'     => 'Subscription Transactions',
                'translate' => 'معاملات الاشتراكات',
                'icon'      => 'subscription-transactions',
                'path'      => '/payments/subscription-transactions',
                'order'     => 2,
            ],
            [
                'name'      => 'payment-active-subscriptions',
                'title'     => 'Active Subscriptions',
                'translate' => 'الاشتراكات النشطة',
                'icon'      => 'active-subscriptions',
                'path'      => '/payments/active-subscriptions',
                'order'     => 3,
            ],
        ];

        foreach ($children as $child) {
            AdminMenu::create([
                'parent_id'      => $parent->id,
                'name'           => $child['name'],
                'title'          => $child['title'],
                'translate'      => $child['translate'],
                'icon'           => $child['icon'],
                'path'           => $child['path'],
                'type'           => 'item',
                'permission'     => 'payments.view',
                'roles'          => ['admin', 'Manager'],
                'order'          => $child['order'],
                'is_main_parent' => false,
                'is_active'      => true,
                'hidden'         => false,
                'disabled'       => false,
                'external'       => false,
                'target'         => false,
                'exact_match'    => false,
                'breadcrumbs'    => true,
            ]);
        }

        $this->command->info('✅ Payment History menu seeded successfully! Parent ID: ' . $parent->id);
    }
}
