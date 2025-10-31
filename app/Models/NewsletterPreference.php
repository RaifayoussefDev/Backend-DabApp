<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'receive_new_articles',
        'receive_new_listings',
        'receive_promotions',
        'receive_weekly_digest',
        'frequency',
    ];

    protected $casts = [
        'receive_new_articles' => 'boolean',
        'receive_new_listings' => 'boolean',
        'receive_promotions' => 'boolean',
        'receive_weekly_digest' => 'boolean',
    ];

    /**
     * Get the subscriber that owns this preference.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }
}
