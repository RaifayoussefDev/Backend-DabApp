<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Guide extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'author_id',
        'category_id',
        'status',
        'views_count',
        'is_featured',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'views_count' => 'integer',
        'published_at' => 'datetime',
    ];

    // Relations
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GuideCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GuideImage::class)->orderBy('order_position');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(GuideTag::class, 'guide_tag_relations', 'guide_id', 'tag_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(GuideComment::class)
            ->whereNull('parent_id')
            ->where('is_approved', true)
            ->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(GuideComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(GuideLike::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(GuideBookmark::class);
    }

    // Mutators
    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
