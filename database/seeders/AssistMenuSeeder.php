<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminMenu;

class AssistMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if parent already exists
        if (AdminMenu::where('name', 'assist')->exists()) {
            $this->command->warn('Assist menu already exists — skipping.');
            return;
        }

        $parent = AdminMenu::create([
            'name'          => 'assist',
            'title'         => 'Velocity Assist',
            'translate'     => 'المساعدة على الطريق',
            'icon'          => 'LifeBuoy',
            'path'          => '/assist',
            'type'          => 'collapse',
            'permission'    => 'assist.view',
            'roles'         => ['admin', 'Manager'],
            'order'         => 20,
            'is_main_parent' => true,
            'is_active'     => true,
            'hidden'        => false,
            'disabled'      => false,
            'external'      => false,
            'target'        => false,
            'exact_match'   => false,
            'breadcrumbs'   => true,
        ]);

        $children = [
            [
                'name'       => 'assist-dashboard',
                'title'      => 'Dashboard',
                'translate'  => 'لوحة التحكم',
                'path'       => '/assist',
                'permission' => 'assist.view',
                'order'      => 1,
            ],
            [
                'name'       => 'assist-helpers',
                'title'      => 'Helpers',
                'translate'  => 'المساعدون',
                'path'       => '/assist/helpers',
                'permission' => 'assist.view',
                'order'      => 2,
            ],
            [
                'name'       => 'assist-requests',
                'title'      => 'Requests',
                'translate'  => 'الطلبات',
                'path'       => '/assist/requests',
                'permission' => 'assist.view',
                'order'      => 3,
            ],
            [
                'name'       => 'assist-expertise-types',
                'title'      => 'Expertise Types',
                'translate'  => 'أنواع الخدمات',
                'path'       => '/assist/expertise-types',
                'permission' => 'assist.manage',
                'order'      => 4,
            ],
        ];

        foreach ($children as $child) {
            AdminMenu::create([
                'parent_id'     => $parent->id,
                'name'          => $child['name'],
                'title'         => $child['title'],
                'translate'     => $child['translate'],
                'icon'          => null,
                'path'          => $child['path'],
                'type'          => 'item',
                'permission'    => $child['permission'],
                'roles'         => ['admin', 'Manager'],
                'order'         => $child['order'],
                'is_main_parent' => false,
                'is_active'     => true,
                'hidden'        => false,
                'disabled'      => false,
                'external'      => false,
                'target'        => false,
                'exact_match'   => false,
                'breadcrumbs'   => true,
            ]);
        }

        $this->command->info('✅ Velocity Assist menu seeded successfully! Parent ID: ' . $parent->id);
    }
}
