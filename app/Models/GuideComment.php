<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuideComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'guide_id',
        'user_id',
        'parent_id',
        'content',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    // Relations
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GuideComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(GuideComment::class, 'parent_id')
            ->where('is_approved', true)
            ->latest();
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeParentComments($query)
    {
        return $query->whereNull('parent_id');
    }
}
