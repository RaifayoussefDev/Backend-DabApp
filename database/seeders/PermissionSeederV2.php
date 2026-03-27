<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class PermissionSeederV2 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear permissions table and pivot
        // WARNING: This will remove existing assignments. 
        // We might want to use updateOrCreate instead to preserve custom roles.
        // For a "fresh start" of v2, we can truncate.
        
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'description' => 'View dashboard statistics'],

            // Users & Roles
            ['name' => 'users.view', 'description' => 'View users list'],
            ['name' => 'users.create', 'description' => 'Create new users'],
            ['name' => 'users.update', 'description' => 'Update user details'],
            ['name' => 'users.delete', 'description' => 'Delete users'],
            ['name' => 'roles.view', 'description' => 'View roles and permissions'],
            ['name' => 'roles.manage', 'description' => 'Manage roles and assignments'],

            // Listings
            ['name' => 'listings.view', 'description' => 'View all listings'],
            ['name' => 'listings.update', 'description' => 'Update/moderate listings'],
            ['name' => 'listings.delete', 'description' => 'Delete listings'],

            // Events
            ['name' => 'events.view', 'description' => 'View events'],
            ['name' => 'events.create', 'description' => 'Create events'],
            ['name' => 'events.update', 'description' => 'Update events'],
            ['name' => 'events.delete', 'description' => 'Delete events'],
            ['name' => 'events.categories', 'description' => 'Manage event categories'],

            // Guides
            ['name' => 'guides.view', 'description' => 'View guides'],
            ['name' => 'guides.manage', 'description' => 'Full management of guides and tags'],

            // Services
            ['name' => 'services.view', 'description' => 'View services'],
            ['name' => 'services.manage', 'description' => 'Manage service providers and categories'],

            // Promo Codes
            ['name' => 'promo_codes.view', 'description' => 'View promo codes'],
            ['name' => 'promo_codes.manage', 'description' => 'Create and manage promo codes'],

            // Banners
            ['name' => 'banners.manage', 'description' => 'Manage app/web banners'],

            // Reports
            ['name' => 'reports.view', 'description' => 'View user reports'],
            ['name' => 'reports.resolve', 'description' => 'Resolve/Manage reports'],

            // Notifications
            ['name' => 'notifications.send', 'description' => 'Send push notifications'],
            ['name' => 'notifications.settings', 'description' => 'Manage notification templates'],

            // Pricing
            ['name' => 'pricing.manage', 'description' => 'Manage system pricing rules'],

            // Routes
            ['name' => 'routes.view', 'description' => 'View routes'],
            ['name' => 'routes.manage', 'description' => 'Manage routes and checkpoints'],

            // POIs
            ['name' => 'pois.view', 'description' => 'View points of interest'],
            ['name' => 'pois.manage', 'description' => 'Full POI management'],

            // Data / Catalog
            ['name' => 'catalog.motorcycle', 'description' => 'Manage motorcycle brands/models'],
            ['name' => 'catalog.spare_parts', 'description' => 'Manage spare parts catalog'],

            // Settings
            ['name' => 'settings.view', 'description' => 'View system settings'],
            ['name' => 'settings.update', 'description' => 'Update system settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']]
            );
        }

        // Assign all permissions to 'admin' role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $allPermissionIds = Permission::pluck('id')->toArray();
            $adminRole->permissions()->sync($allPermissionIds);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('✅ PermissionSeederV2 executed successfully!');
    }
}
