<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends MarketplaceModel
{
    protected $table = 'marketplace_product_attributes';

    protected $fillable = ['product_id', 'label', 'label_ar', 'value', 'value_ar', 'order_position'];

    protected $casts = ['order_position' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
