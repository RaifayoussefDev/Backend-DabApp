<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear role_permission pivot table first
        DB::table('role_permissions')->truncate();

        // Clear permissions table
        Permission::truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $permissions = [
            // ============================================
            // Dashboard
            // ============================================
            ['name' => 'view_dashboard', 'description' => 'View admin dashboard'],

            // ============================================
            // User Management (Parent Menu)
            // ============================================
            ['name' => 'manage_users', 'description' => 'Full user management access'],

            // User Management - Children
            ['name' => 'view_users', 'description' => 'View users list'],
            ['name' => 'manage_roles', 'description' => 'Manage roles and permissions'],
            ['name' => 'view_deleted_users', 'description' => 'View deleted users'],
            ['name' => 'view_auth_logs', 'description' => 'View authentication logs'],
            ['name' => 'view_password_resets', 'description' => 'View password reset requests'],

            // ============================================
            // Pricing Management (Parent Menu)
            // ============================================
            ['name' => 'manage_pricing', 'description' => 'Full pricing management access'],

            // Pricing Management - Children
            ['name' => 'manage_motorcycle_pricing', 'description' => 'Manage motorcycle pricing rules'],
            ['name' => 'manage_sparepart_pricing', 'description' => 'Manage spare part pricing rules'],
            ['name' => 'manage_plate_pricing', 'description' => 'Manage license plate pricing rules'],
        ];

        // Insert all permissions
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        $this->command->info('✅ ' . count($permissions) . ' permissions created successfully!');

        // Display summary
        $this->command->newLine();
        $this->command->info('📊 Menu Permissions Summary:');
        $this->command->table(
            ['Permission Name', 'Description'],
            array_map(function($p) {
                return [$p['name'], $p['description']];
            }, $permissions)
        );

        $this->command->newLine();
        $this->command->info('🎯 Permissions breakdown:');
        $this->command->line('   - Dashboard: 1 permission');
        $this->command->line('   - User Management: 6 permissions (1 parent + 5 children)');
        $this->command->line('   - Pricing Management: 4 permissions (1 parent + 3 children)');
        $this->command->line('   📦 Total: ' . count($permissions) . ' permissions');
    }
}
