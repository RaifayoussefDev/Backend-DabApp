<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mirrors GuideLike. */
class ProductLike extends MarketplaceModel
{
    protected $table = 'marketplace_product_likes';

    protected $fillable = ['product_id', 'user_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
