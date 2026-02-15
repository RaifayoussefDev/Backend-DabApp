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
        'title_ar',
        'slug',
        'content',
        'content_ar',
        'excerpt',
        'excerpt_ar',
        'featured_image',
        'author_id',
        'category_id',
        'status',
        'views_count',
        'is_featured',
        'published_at',
        'meta_title',
        'meta_title_ar',
        'meta_description',
        'meta_description_ar',
        'meta_keywords',
        'meta_keywords_ar',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'views_count' => 'integer',
        'published_at' => 'datetime',
    ];

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

    // ✅ NOUVELLE RELATION
    public function sections(): HasMany
    {
        return $this->hasMany(GuideSection::class)->orderBy('order_position');
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

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
