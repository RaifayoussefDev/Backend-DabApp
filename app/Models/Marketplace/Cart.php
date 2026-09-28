<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends MarketplaceModel
{
    protected $table = 'marketplace_carts';

    protected $fillable = ['user_id', 'session_id', 'status', 'last_activity_at'];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }
}
