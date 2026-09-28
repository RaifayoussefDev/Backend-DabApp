<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQuestion extends MarketplaceModel
{
    protected $table = 'marketplace_product_questions';

    protected $fillable = ['product_id', 'user_id', 'question', 'answer', 'answered_at', 'answered_by', 'is_approved'];

    protected $casts = [
        'answered_at' => 'datetime',
        'is_approved' => 'boolean',
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

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
