<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends MarketplaceModel
{
    protected $table = 'marketplace_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'vendor_id', 'shipping_method_id',
        'product_name', 'product_reference', 'quantity', 'unit_price', 'total_price',
        'shipping_fee', 'discount_amount',
        'commission_rate', 'commission_amount', 'vendor_net', 'payout_id',
        'fulfillment_status', 'tracking_number', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'unit_price'        => 'decimal:2',
        'total_price'       => 'decimal:2',
        'shipping_fee'      => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'commission_rate'   => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'vendor_net'        => 'decimal:2',
        'shipped_at'        => 'datetime',
        'delivered_at'      => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(VendorShippingMethod::class, 'shipping_method_id')->withTrashed();
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(VendorPayout::class, 'payout_id');
    }
}
