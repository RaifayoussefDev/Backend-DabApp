<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends MarketplaceModel
{
    protected $table = 'marketplace_products';

    protected $fillable = [
        'vendor_id', 'category_id', 'brand_id',
        'reference', 'slug', 'name', 'name_ar', 'description', 'description_ar',
        'price', 'compare_at_price', 'stock_quantity', 'condition', 'status', 'status_reason', 'is_featured', 'published_at',
        'rating_avg', 'reviews_count', 'likes_count', 'sales_count',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity'   => 'integer',
        'is_featured'      => 'boolean',
        'published_at'     => 'datetime',
        'rating_avg'       => 'decimal:2',
        'reviews_count'    => 'integer',
        'likes_count'      => 'integer',
        'sales_count'      => 'integer',
    ];

    /** Only what the storefront may see: an active product of an active vendor. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('vendor', fn (Builder $v) => $v->where('status', 'active'));
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /** Parts that fit a bike: explicit compatibility rows plus universal parts. */
    public function scopeCompatibleWith(Builder $query, ?int $brandId, ?int $modelId = null, ?int $yearId = null): Builder
    {
        return $query->whereHas('compatibility', function (Builder $c) use ($brandId, $modelId, $yearId) {
            $c->where(function (Builder $w) use ($brandId, $modelId, $yearId) {
                $w->where('is_universal', true)
                    ->orWhere(function (Builder $m) use ($brandId, $modelId, $yearId) {
                        $m->when($brandId, fn ($q) => $q->where('moto_brand_id', $brandId))
                            ->when($modelId, fn ($q) => $q->where('moto_model_id', $modelId))
                            ->when($yearId, fn ($q) => $q->where('moto_year_id', $yearId));
                    });
            });
        });
    }

    /** Ignores variant stock on purpose: call inStockEffective() once variants are loaded. */
    public function getInStockAttribute(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Pricing that accounts for variants (assumes `variants` is already eager-loaded: no query here).
     * A product with no variants uses its own price/compare_at_price/stock_quantity directly.
     *
     * @return array{price: float, price_max: ?float, compare_at_price: ?float, in_stock: bool}
     */
    public function effectivePricing(): array
    {
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            $cheapest = $this->variants->sortBy('price')->first();
            $min = (float) $this->variants->min('price');
            $max = (float) $this->variants->max('price');

            return [
                'price'            => $min,
                'price_max'        => $max > $min ? $max : null,
                'compare_at_price' => $cheapest->compare_at_price !== null ? (float) $cheapest->compare_at_price : null,
                'in_stock'         => $this->variants->sum('stock_quantity') > 0,
            ];
        }

        return [
            'price'            => (float) $this->price,
            'price_max'        => null,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'in_stock'         => $this->stock_quantity > 0,
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->orderBy('order_position');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('order_position');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_id')->orderByDesc('is_cover')->orderBy('order_position');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class, 'product_id')->orderBy('order_position');
    }

    public function compatibility(): HasMany
    {
        return $this->hasMany(ProductMotorcycle::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class, 'product_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class, 'product_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ProductLike::class, 'product_id');
    }
}
