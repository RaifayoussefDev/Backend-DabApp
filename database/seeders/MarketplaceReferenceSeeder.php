<?php

namespace Database\Seeders;

use App\Models\Marketplace\Category;
use App\Models\Marketplace\ShippingProvider;
use Illuminate\Database\Seeder;

/**
 * Reference data the marketplace needs to function (safe to run on any environment, idempotent):
 * the shipping providers list and the base category tree.
 *
 *   php artisan db:seed --class=MarketplaceReferenceSeeder
 */
class MarketplaceReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedShippingProviders();
        $this->seedCategories();
    }

    private function seedShippingProviders(): void
    {
        $providers = [
            ['code' => 'dhl',    'name' => 'DHL',              'requires_api_key' => true],
            ['code' => 'aramex', 'name' => 'Aramex',           'requires_api_key' => true],
            ['code' => 'smsa',   'name' => 'SMSA',             'requires_api_key' => true],
            ['code' => 'dabapp', 'name' => 'DabApp Shipping',  'requires_api_key' => false],
            ['code' => 'manual', 'name' => 'Manual',           'requires_api_key' => false],
        ];

        foreach ($providers as $provider) {
            ShippingProvider::withTrashed()->updateOrCreate(
                ['code' => $provider['code']],
                $provider + ['is_active' => true, 'deleted_at' => null]
            );
        }
    }

    private function seedCategories(): void
    {
        $tree = [
            ['parts', 'Parts', 'قطع الغيار', [
                ['tyres', 'Tyres', 'إطارات', [
                    ['tyres-front', 'Front tyres', 'إطارات أمامية'],
                    ['tyres-rear', 'Rear tyres', 'إطارات خلفية'],
                    ['tyre-tubes', 'Inner tubes', 'أنابيب داخلية'],
                ]],
                ['brakes', 'Brakes', 'الفرامل', [
                    ['brake-pads', 'Brake pads', 'بطانات الفرامل'],
                    ['brake-discs', 'Brake discs', 'أقراص الفرامل'],
                ]],
                ['engine-exhaust', 'Engine & Exhaust', 'المحرك والعادم', [
                    ['exhausts', 'Exhausts', 'شكمانات'],
                    ['air-filters', 'Air filters', 'فلاتر الهواء'],
                    ['oil-filters', 'Oil filters', 'فلاتر الزيت'],
                ]],
                ['chain-sprockets', 'Chain & Sprockets', 'السلسلة والتروس'],
                ['batteries', 'Batteries', 'البطاريات'],
            ]],
            ['oils-care', 'Oils & Care', 'الزيوت والعناية', [
                ['engine-oil', 'Engine oil', 'زيت المحرك'],
                ['chain-care', 'Chain care', 'عناية السلسلة'],
            ]],
            ['accessories', 'Accessories', 'الإكسسوارات', [
                ['luggage', 'Luggage & bags', 'الحقائب'],
                ['phone-mounts', 'Phone mounts', 'حوامل الهاتف'],
                ['security', 'Locks & security', 'الأقفال والأمان'],
                ['covers', 'Covers', 'الأغطية'],
            ]],
            ['riding-gear', 'Riding gear', 'ملابس الراكب', [
                ['helmets', 'Helmets', 'الخوذات'],
                ['jackets', 'Jackets', 'الجاكيتات'],
                ['gloves', 'Gloves', 'القفازات'],
                ['boots', 'Boots', 'الأحذية'],
            ]],
        ];

        $this->seedLevel($tree, null);
    }

    private function seedLevel(array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $position => $node) {
            [$slug, $name, $nameAr] = $node;
            $children = $node[3] ?? [];

            $category = Category::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'parent_id'      => $parentId,
                    'name'           => $name,
                    'name_ar'        => $nameAr,
                    'order_position' => $position,
                    'is_active'      => true,
                    'deleted_at'     => null,
                ]
            );

            if ($children) {
                $this->seedLevel($children, $category->id);
            }
        }
    }
}
