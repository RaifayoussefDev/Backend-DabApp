<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors GuideComment. */
class ProductComment extends MarketplaceModel
{
    protected $table = 'marketplace_product_comments';

    protected $fillable = ['product_id', 'user_id', 'parent_id', 'content', 'is_approved'];

    protected $casts = ['is_approved' => 'boolean'];

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('is_approved', true)->orderBy('created_at');
    }
}
