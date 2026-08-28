<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\AdminMenu;

/**
 * Turns the flat "Users" entry into a parent with two children:
 *   - All Users            → /users               (keeps the list reachable)
 *   - Guest Traceability   → /users/guest-activity (anonymous views + contact reveals)
 *
 * Additive `firstOrCreate` only — the AdminMenuV2Seeder truncates/rebuilds the
 * whole tree, so we never touch existing rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = AdminMenu::where('name', 'users')->whereNull('parent_id')->first();

        if (!$parent) {
            return;
        }

        AdminMenu::firstOrCreate(
            ['name' => 'users-all'],
            [
                'parent_id'      => $parent->id,
                'title'          => 'All Users',
                'translate'      => 'كل المستخدمين',
                'icon'           => 'Users',
                'path'           => '/users',
                'permission'     => 'users.view',
                'order'          => 1,
                'type'           => 'item',
                'roles'          => ['admin'],
                'is_main_parent' => false,
                'is_active'      => true,
            ]
        );

        AdminMenu::firstOrCreate(
            ['name' => 'users-guest-activity'],
            [
                'parent_id'      => $parent->id,
                'title'          => 'Guest Traceability',
                'translate'      => 'تتبّع الزوّار',
                'icon'           => 'Footprints',
                'path'           => '/users/guest-activity',
                'permission'     => 'users.view',
                'order'          => 2,
                'type'           => 'item',
                'roles'          => ['admin'],
                'is_main_parent' => false,
                'is_active'      => true,
            ]
        );
    }

    public function down(): void
    {
        AdminMenu::whereIn('name', ['users-all', 'users-guest-activity'])->delete();
    }
};
