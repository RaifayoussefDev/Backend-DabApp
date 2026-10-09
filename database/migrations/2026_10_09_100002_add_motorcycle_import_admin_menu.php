<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\AdminMenu;

/**
 * Adds "Import Data" under the Motorcycle menu → /motorcycle/import
 * (bulk upsert of the catalog from the scraper spreadsheet).
 *
 * Additive `firstOrCreate` only — the AdminMenuV2Seeder truncates/rebuilds the
 * whole tree, so we never touch existing rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = AdminMenu::where('name', 'motorcycle')->whereNull('parent_id')->first();

        if (!$parent) {
            return;
        }

        AdminMenu::firstOrCreate(
            ['name' => 'motorcycle-import'],
            [
                'parent_id'      => $parent->id,
                'title'          => 'Import Data',
                'translate'      => 'استيراد البيانات',
                'icon'           => 'Upload',
                'path'           => '/motorcycle/import',
                'permission'     => 'catalog.motorcycle',
                'order'          => 5,
                'type'           => 'item',
                'roles'          => $parent->roles ?: ['admin'],
                'is_main_parent' => false,
                'is_active'      => true,
            ]
        );
    }

    public function down(): void
    {
        AdminMenu::where('name', 'motorcycle-import')->delete();
    }
};
