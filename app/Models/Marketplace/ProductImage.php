<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends MarketplaceModel
{
    protected $table = 'marketplace_product_images';

    protected $fillable = ['product_id', 'image_path', 'is_cover', 'order_position'];

    protected $casts = [
        'is_cover'       => 'boolean',
        'order_position' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->storageUrl($this->image_path);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
