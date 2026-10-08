<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends MarketplaceModel
{
    protected $table = 'marketplace_categories';

    protected $fillable = [
        'parent_id', 'name', 'name_ar', 'slug', 'description', 'image_path', 'order_position', 'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'order_position' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->storageUrl($this->image_path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order_position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Visible products per category id, rolled up to every ancestor (a parent counts its sub-categories).
     *
     * @return array<int,int>
     */
    public static function productCounts(): array
    {
        $direct = Product::visible()->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $parents = static::query()->pluck('parent_id', 'id');

        $totals = [];
        foreach ($direct as $categoryId => $count) {
            $id = $categoryId;
            for ($depth = 0; $id && $depth < 50; $depth++) {
                $totals[$id] = ($totals[$id] ?? 0) + $count;
                $id = $parents[$id] ?? null;
            }
        }

        return $totals;
    }

    /** IDs of this category and all its descendants (unlimited depth), for "category includes sub-categories" filters. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier) {
            $frontier = self::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** Ancestors from the root down to (and excluding) this category. */
    public function breadcrumb(): array
    {
        $trail = [];
        $node = $this->parent;

        while ($node) {
            array_unshift($trail, $node);
            $node = $node->parent;
        }

        return $trail;
    }
}
