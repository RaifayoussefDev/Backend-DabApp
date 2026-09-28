<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends MarketplaceModel
{
    protected $table = 'marketplace_wishlists';

    protected $fillable = ['user_id', 'product_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
