<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSend extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'sent_at',
        'opened_at',
        'clicked_at',
        'bounced',
        'unsubscribed',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced' => 'boolean',
        'unsubscribed' => 'boolean',
    ];

    /**
     * Get the campaign this send belongs to.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'campaign_id');
    }

    /**
     * Get the subscriber this send was sent to.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    /**
     * Check if the email was opened.
     */
    public function isOpened(): bool
    {
        return !is_null($this->opened_at);
    }

    /**
     * Check if the email was clicked.
     */
    public function isClicked(): bool
    {
        return !is_null($this->clicked_at);
    }
}
