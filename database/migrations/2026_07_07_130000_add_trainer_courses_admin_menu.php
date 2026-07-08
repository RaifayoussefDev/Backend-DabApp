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
        $parent = AdminMenu::where('name', 'trainer')->whereNull('parent_id')->first();

        if (!$parent) {
            return;
        }

        AdminMenu::firstOrCreate(
            ['name' => 'trainer-courses'],
            [
                'parent_id'      => $parent->id,
                'title'          => 'Courses',
                'translate'      => 'الدورات',
                'icon'           => 'BookOpen',
                'path'           => '/trainer/courses',
                'permission'     => 'trainers.view',
                'order'          => 6,
                'type'           => 'item',
                'roles'          => ['admin', 'Manager'],
                'is_main_parent' => false,
                'is_active'      => true,
            ]
        );
    }

    public function down(): void
    {
        AdminMenu::where('name', 'trainer-courses')->delete();
    }
};
