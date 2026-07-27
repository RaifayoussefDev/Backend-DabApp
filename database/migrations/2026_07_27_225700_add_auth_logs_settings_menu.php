<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\AdminMenu;

return new class extends Migration
{
    /**
     * Additive insert only — the AdminMenuV2Seeder truncates and rebuilds the whole
     * menu tree, which would wipe any customization already made in production.
     */
    public function up(): void
    {
        $parent = AdminMenu::where('name', 'settings')->whereNull('parent_id')->first();

        if (!$parent) {
            return;
        }

        AdminMenu::firstOrCreate(
            ['name' => 'settings-auth-logs'],
            [
                'parent_id'      => $parent->id,
                'title'          => 'Auth Logs',
                'translate'      => 'سجلات المصادقة',
                'icon'           => 'ShieldCheck',
                'path'           => '/settings/auth-logs',
                'permission'     => 'settings.view',
                'order'          => 7,
                'type'           => 'item',
                'roles'          => ['admin'],
                'is_main_parent' => false,
                'is_active'      => true,
            ]
        );
    }

    public function down(): void
    {
        AdminMenu::where('name', 'settings-auth-logs')->delete();
    }
};
