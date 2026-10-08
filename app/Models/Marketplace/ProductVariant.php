<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends MarketplaceModel
{
    protected $table = 'marketplace_product_variants';

    protected $fillable = [
        'product_id', 'sku', 'option1_name', 'option1_value', 'color_hex', 'option2_name', 'option2_value',
        'price', 'compare_at_price', 'stock_quantity', 'image_path', 'is_default', 'order_position',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity'   => 'integer',
        'is_default'       => 'boolean',
        'order_position'   => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->storageUrl($this->image_path);
    }

    public function getInStockAttribute(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
