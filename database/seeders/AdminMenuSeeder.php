<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminMenu;

class AdminMenuSeeder extends Seeder
{
    public function run()
    {
        // Clear existing menus
        AdminMenu::truncate();

        // 1. Dashboard
        $dashboard = AdminMenu::create([
            'name' => 'Dashboard',
            'icon' => 'dashboard',
            'route' => '/admin/dashboard',
            'permission' => 'view_dashboard',
            'order' => 1,
            'is_active' => true
        ]);

        // 2. User Management
        $userManagement = AdminMenu::create([
            'name' => 'User Management',
            'icon' => 'users',
            'route' => null,
            'permission' => 'manage_users',
            'order' => 2,
            'is_active' => true
        ]);

        // User Management Children
        AdminMenu::create([
            'parent_id' => $userManagement->id,
            'name' => 'All Users',
            'route' => '/admin/users',
            'permission' => 'view_users',
            'order' => 1
        ]);

        AdminMenu::create([
            'parent_id' => $userManagement->id,
            'name' => 'Roles & Permissions',
            'route' => '/admin/roles',
            'permission' => 'manage_roles',
            'order' => 2
        ]);

        AdminMenu::create([
            'parent_id' => $userManagement->id,
            'name' => 'Active Users',
            'route' => '/admin/users/active',
            'permission' => 'view_users',
            'order' => 3
        ]);

        AdminMenu::create([
            'parent_id' => $userManagement->id,
            'name' => 'Deleted Users',
            'route' => '/admin/users/deleted',
            'permission' => 'view_deleted_users',
            'order' => 4
        ]);

        AdminMenu::create([
            'parent_id' => $userManagement->id,
            'name' => 'Authentication Logs',
            'route' => '/admin/authentication-logs',
            'permission' => 'view_auth_logs',
            'order' => 5
        ]);

        AdminMenu::create([
            'parent_id' => $userManagement->id,
            'name' => 'Password Resets',
            'route' => '/admin/password-resets',
            'permission' => 'view_password_resets',
            'order' => 6
        ]);

        // 3. Pricing Management
        $pricingManagement = AdminMenu::create([
            'name' => 'Pricing Management',
            'icon' => 'trending-up',
            'route' => null,
            'permission' => 'manage_pricing',
            'order' => 3,
            'is_active' => true
        ]);

        // Pricing Management Children
        AdminMenu::create([
            'parent_id' => $pricingManagement->id,
            'name' => 'Motorcycle Pricing',
            'route' => '/admin/pricing-rules/motorcycles',
            'permission' => 'manage_motorcycle_pricing',
            'order' => 1
        ]);

        AdminMenu::create([
            'parent_id' => $pricingManagement->id,
            'name' => 'Spare Part Pricing',
            'route' => '/admin/pricing-rules/spare-parts',
            'permission' => 'manage_sparepart_pricing',
            'order' => 2
        ]);

        AdminMenu::create([
            'parent_id' => $pricingManagement->id,
            'name' => 'License Plate Pricing',
            'route' => '/admin/pricing-rules/license-plates',
            'permission' => 'manage_plate_pricing',
            'order' => 3
        ]);

        $this->command->info('Admin menus seeded successfully!');
    }
}
