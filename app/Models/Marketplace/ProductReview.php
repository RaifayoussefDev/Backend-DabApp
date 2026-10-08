<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends MarketplaceModel
{
    protected $table = 'marketplace_product_reviews';

    protected $fillable = [
        'product_id', 'user_id', 'order_item_id', 'rating', 'title', 'comment', 'tags', 'images',
        'is_anonymous', 'verified_purchase', 'is_approved',
    ];

    protected $casts = [
        'rating'            => 'integer',
        'tags'              => 'array',
        'images'            => 'array',
        'is_anonymous'      => 'boolean',
        'verified_purchase' => 'boolean',
        'is_approved'       => 'boolean',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
