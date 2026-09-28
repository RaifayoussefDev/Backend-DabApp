<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends MarketplaceModel
{
    protected $table = 'marketplace_cart_items';

    protected $fillable = ['cart_id', 'product_id', 'vendor_id', 'quantity', 'unit_price_snapshot'];

    protected $casts = [
        'quantity'            => 'integer',
        'unit_price_snapshot' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
