<?php

namespace App\Http\Controllers\Marketplace\Concerns;

use App\Http\Controllers\Marketplace\ProductController;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared between Admin\Marketplace\ProductAdminController and Marketplace\VendorProductController:
 * both manage the same images/attributes/compatibility/variants full-replace arrays on a product and
 * present it the same way. The two controllers differ only in who may call them and which top-level
 * fields (vendor_id, status, is_featured) are writable - that part stays in each controller.
 */
trait ManagesProductChildren
{
    /** Validation rules for the four nested full-replace arrays only - merge with each controller's own top-level field rules. */
    protected function childRules(?int $ignoreProductId = null): array
    {
        // Variants are deleted and recreated on every sync (syncProductChildren), so re-sending an unchanged
        // SKU for the SAME product must not look like a collision: only another product's variant counts.
        $variantSkuRule = Rule::unique('marketplace_product_variants', 'sku');
        if ($ignoreProductId) {
            $variantSkuRule = $variantSkuRule->where(fn ($q) => $q->where('product_id', '!=', $ignoreProductId));
        }

        return [
            'images'                   => 'nullable|array',
            'images.*.image_path'      => $this->imagePathRule(),
            'images.*.is_cover'        => 'nullable|boolean',
            'images.*.order_position'  => 'nullable|integer|min:0',

            'attributes'                  => 'nullable|array',
            'attributes.*.label'          => 'required_with:attributes|string|max:255',
            'attributes.*.label_ar'       => 'nullable|string|max:255',
            'attributes.*.value'          => 'required_with:attributes|string|max:255',
            'attributes.*.value_ar'       => 'nullable|string|max:255',
            'attributes.*.order_position' => 'nullable|integer|min:0',

            'compatibility'                     => 'nullable|array',
            'compatibility.*.moto_brand_id'      => ['nullable', 'integer', Rule::exists('motorcycle_brands', 'id')],
            'compatibility.*.bike_type_id'       => ['nullable', 'integer', Rule::exists('motorcycle_types', 'id')],
            'compatibility.*.moto_model_id'      => ['nullable', 'integer', Rule::exists('motorcycle_models', 'id')],
            'compatibility.*.moto_year_id'       => ['nullable', 'integer', Rule::exists('motorcycle_years', 'id')],
            'compatibility.*.is_universal'       => 'nullable|boolean',

            'variants'                        => 'nullable|array',
            'variants.*.sku'                  => ['required_with:variants', 'string', 'max:255', 'distinct', $variantSkuRule],
            'variants.*.option1_name'         => 'nullable|string|max:100',
            'variants.*.option1_value'        => 'nullable|string|max:100',
            'variants.*.color_hex'            => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'variants.*.option2_name'         => 'nullable|string|max:100',
            'variants.*.option2_value'        => 'nullable|string|max:100',
            'variants.*.price'                => 'required_with:variants|numeric|min:0',
            'variants.*.compare_at_price'      => 'nullable|numeric|min:0',
            'variants.*.stock_quantity'        => 'nullable|integer|min:0',
            'variants.*.image_path'            => $this->imagePathRule(),
            'variants.*.is_default'            => 'nullable|boolean',
        ];
    }

    /** images / attributes / compatibility are full-replace: present in the request = delete the old set, insert the new one. */
    protected function syncProductChildren(Product $product, Request $request): void
    {
        if ($request->has('images')) {
            $product->images()->delete();

            $hasCover = false;
            foreach ($request->input('images', []) as $i => $img) {
                $isCover = (bool) ($img['is_cover'] ?? false);
                $hasCover = $hasCover || $isCover;

                $product->images()->create([
                    'image_path'     => $img['image_path'],
                    'is_cover'       => $isCover,
                    'order_position' => $img['order_position'] ?? $i,
                ]);
            }

            if (! $hasCover) {
                $product->images()->orderBy('order_position')->first()?->update(['is_cover' => true]);
            }
        }

        if ($request->has('attributes')) {
            $product->attributes()->delete();

            foreach ($request->input('attributes', []) as $i => $attr) {
                $product->attributes()->create([
                    'label'          => $attr['label'],
                    'label_ar'       => $attr['label_ar'] ?? null,
                    'value'          => $attr['value'],
                    'value_ar'       => $attr['value_ar'] ?? null,
                    'order_position' => $attr['order_position'] ?? $i,
                ]);
            }
        }

        if ($request->has('compatibility')) {
            $product->compatibility()->delete();

            foreach ($request->input('compatibility', []) as $c) {
                $product->compatibility()->create([
                    'moto_brand_id' => $c['moto_brand_id'] ?? null,
                    'bike_type_id'  => $c['bike_type_id'] ?? null,
                    'moto_model_id' => $c['moto_model_id'] ?? null,
                    'moto_year_id'  => $c['moto_year_id'] ?? null,
                    'is_universal'  => (bool) ($c['is_universal'] ?? false),
                ]);
            }
        }

        if ($request->has('variants')) {
            // Hard delete, not soft: `sku` is DB-unique, and a full-replace means these rows are genuinely gone -
            // a soft-deleted row would still collide if the same sku is sent again on the very next update.
            $product->variants()->forceDelete();

            $hasDefault = false;
            foreach ($request->input('variants', []) as $i => $v) {
                $isDefault = (bool) ($v['is_default'] ?? false);
                $hasDefault = $hasDefault || $isDefault;

                $product->variants()->create([
                    'sku'              => $v['sku'],
                    'option1_name'     => $v['option1_name'] ?? null,
                    'option1_value'    => $v['option1_value'] ?? null,
                    'color_hex'        => $v['color_hex'] ?? null,
                    'option2_name'     => $v['option2_name'] ?? null,
                    'option2_value'    => $v['option2_value'] ?? null,
                    'price'            => $v['price'],
                    'compare_at_price' => $v['compare_at_price'] ?? null,
                    'stock_quantity'   => (int) ($v['stock_quantity'] ?? 0),
                    'image_path'       => $v['image_path'] ?? null,
                    'is_default'       => $isDefault,
                    'order_position'   => $i,
                ]);
            }

            if (! $hasDefault) {
                $product->variants()->orderBy('order_position')->first()?->update(['is_default' => true]);
            }
        }
    }

    protected function loadProductFull(Product $product): Product
    {
        return $product->load([
            'vendor:id,slug,shop_name', 'category:id,slug,name', 'brand:id,name',
            'images', 'attributes', 'variants', 'compatibility.brand:id,name', 'compatibility.model:id,name', 'compatibility.year:id,year', 'compatibility.bikeType:id,name',
        ]);
    }

    /** Same shape for admin and the vendor's own view: nothing here is sensitive beyond what the owner should see. $full adds images/attributes/compatibility/variants (detail); the list only gets a cover thumbnail. */
    protected function presentProduct(Product $product, bool $full = false): array
    {
        $pricing = $product->effectivePricing();

        $data = [
            'id'               => $product->id,
            'vendor'           => $product->vendor ? ['id' => $product->vendor->id, 'slug' => $product->vendor->slug, 'shop_name' => $product->vendor->shop_name] : null,
            'category'         => $product->category ? ['id' => $product->category->id, 'slug' => $product->category->slug, 'name' => $product->category->name] : null,
            'brand'            => $product->brand ? ['id' => $product->brand->id, 'name' => $product->brand->name] : null,
            'reference'        => $product->reference,
            'slug'             => $product->slug,
            'name'             => $product->name,
            'name_ar'          => $product->name_ar,
            'description'      => $product->description,
            'description_ar'   => $product->description_ar,
            'price'            => $pricing['price'],
            'price_max'        => $pricing['price_max'],
            'compare_at_price' => $pricing['compare_at_price'],
            'in_stock'         => $pricing['in_stock'],
            'stock_quantity'   => $product->stock_quantity,
            'has_variants'     => $product->relationLoaded('variants') ? $product->variants->isNotEmpty() : false,
            'condition'        => $product->condition,
            'status'           => $product->status,
            'status_reason'    => $product->status_reason,
            'is_featured'      => (bool) $product->is_featured,
            'published_at'     => $product->published_at,
            'rating_avg'       => (float) $product->rating_avg,
            'reviews_count'    => $product->reviews_count,
            'likes_count'      => $product->likes_count,
            'sales_count'      => $product->sales_count,
            'created_at'       => $product->created_at,
        ];

        if ($full) {
            $data['images'] = $product->images->map(fn ($i) => [
                'id' => $i->id, 'image_url' => $i->image_url, 'is_cover' => (bool) $i->is_cover, 'order_position' => $i->order_position,
            ])->values();
            $data['attributes'] = $product->attributes->map(fn ($a) => [
                'id' => $a->id, 'label' => $a->label, 'label_ar' => $a->label_ar, 'value' => $a->value, 'value_ar' => $a->value_ar,
            ])->values();
            $data['compatibility'] = $product->compatibility->map(fn ($c) => [
                'id' => $c->id, 'is_universal' => (bool) $c->is_universal,
                'moto_brand_id' => $c->moto_brand_id, 'brand' => $c->brand->name ?? null,
                'bike_type_id'  => $c->bike_type_id, 'bike_type' => $c->bikeType->name ?? null,
                'moto_model_id' => $c->moto_model_id, 'model' => $c->model->name ?? null,
                'moto_year_id'  => $c->moto_year_id, 'year' => $c->year->year ?? null,
            ])->values();
            $data['variants'] = $product->variants->map(fn (ProductVariant $v) => array_merge(['id' => $v->id], ProductController::variantRow($v)))->values();
        } else {
            $data['cover_image_url'] = $product->coverImage?->image_url;
        }

        return $data;
    }

    /** reference (SKU) generated from the name when left out, same idea as the slug: uppercased, dash-separated, -2/-3... appended on a collision. */
    protected function uniqueReference(string $name): string
    {
        $base = strtoupper(Str::slug($name)) ?: 'PRODUCT';
        $reference = $base;

        for ($n = 2; Product::withTrashed()->where('reference', $reference)->exists(); $n++) {
            $reference = $base . '-' . $n;
        }

        return $reference;
    }

    /** Validation rule for an image field: the string returned by POST /api/marketplace/upload-image, never a raw file. */
    protected function imagePathRule(): array
    {
        return ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
            if ($value && ! str_contains($value, '/storage/marketplace/')) {
                $fail('The ' . $attribute . ' must be a URL returned by POST /api/marketplace/upload-image.');
            }
        }];
    }
}
