<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Listing",
 *     title="Listing",
 *     description="Listing model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Motorcycle for sale"),
 *     @OA\Property(property="description", type="string", example="Good condition"),
 *     @OA\Property(property="price", type="number", example=10000),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="seller_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'price',
        'price_type',
        'seller_id',
        'category_id',
        'country_id',
        'city_id',
        'status',
        'published_at',
        'auction_enabled',
        'minimum_bid',
        'allow_submission',
        'listing_type_id',
        'contacting_channel',
        'seller_type',
        'payment_pending',
        'step',
        'edit_count',
        'last_edited_at',
        'created_by', // ✅ Added
        'views_count', // ✅ Added
        'follow_up_sent_at',
        'follow_up_responded_at',
        'follow_up_response',
        'sale_channel',
        'reason_not_sold',
        'follow_up_source',
        'follow_up_set_by',
        'follow_up_count',
        'next_follow_up_at',
    ];

    // ✅ AJOUT DES CASTS
    protected $casts = [
        'price' => 'decimal:2',
        'minimum_bid' => 'decimal:2',
        'auction_enabled' => 'boolean',
        'allow_submission' => 'boolean',
        'payment_pending' => 'boolean',
        'edit_count' => 'integer',           // ✅ NOUVEAU
        'views_count' => 'integer',          // ✅ NOUVEAU
        'last_edited_at' => 'datetime',      // ✅ NOUVEAU
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'follow_up_sent_at' => 'datetime',
        'follow_up_responded_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'follow_up_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Arm the recurring "did it sell?" schedule the moment a listing is
        // (re)published. Covers every publish path without touching them. Only
        // fills a NULL slot, so it never fights the cron / controller updates.
        static::saving(function (Listing $listing) {
            $shouldArm = $listing->status === 'published'
                && $listing->published_at
                && $listing->follow_up_response !== 'sold'
                && $listing->next_follow_up_at === null;

            if ($shouldArm) {
                $listing->next_follow_up_at = $listing->computeNextFollowUpAt();
            }
        });
    }

    // ===========================
    // RELATIONS EXISTANTES
    // ===========================

    public function auctions()
    {
        return $this->hasMany(AuctionHistory::class);
    }

    public function motorcycle()
    {
        return $this->hasOne(Motorcycle::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The admin who last set the sale diagnostic manually (null when the seller
     * answered the follow-up themselves).
     */
    public function followUpSetBy()
    {
        return $this->belongsTo(User::class, 'follow_up_set_by');
    }

    public function wishlistedBy()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function images()
    {
        return $this->hasMany(ListingImage::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function listingType()
    {
        return $this->belongsTo(ListingType::class);
    }

    public function motorcycleBrand()
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id');
    }

    public function motorcycleModel()
    {
        return $this->belongsTo(MotorcycleModel::class, 'model_id');
    }

    public function motorcycleYear()
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }

    public function motorcycleType()
    {
        return $this->belongsTo(MotorcycleType::class, 'type_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function sparePart()
    {
        return $this->hasOne(SparePart::class);
    }

    public function licensePlate()
    {
        return $this->hasOne(LicensePlate::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    public function auctionHistories()
    {
        return $this->hasMany(AuctionHistory::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Get all of the listing's views.
     */
    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    // ===========================
    // SCOPES EXISTANTS
    // ===========================

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Recurring "did it sell?" reminder cadence, in days after `published_at`,
     * keyed by how many reminders were already sent.
     *   0 sent → J+7   |   1 → J+17   |   2 → J+32   |   3+ → +30 days each
     */
    public static function followUpOffsetDays(int $sentCount): int
    {
        return match (true) {
            $sentCount <= 0  => 7,
            $sentCount === 1 => 17,
            $sentCount === 2 => 32,
            default          => 32 + ($sentCount - 2) * 30,
        };
    }

    /**
     * The absolute time the next reminder is due, anchored to `published_at` so
     * the cadence doesn't drift. If that anchor point is already in the past
     * (old listing that just entered the schedule), floor it to 30 days out so a
     * backlog listing gets ONE reminder now and then the normal monthly cadence —
     * never a burst of catch-up reminders on consecutive daily runs.
     */
    public function computeNextFollowUpAt(?int $forCount = null): ?\Illuminate\Support\Carbon
    {
        if (!$this->published_at) {
            return null;
        }

        $count = $forCount ?? (int) $this->follow_up_count;
        $natural = $this->published_at->copy()->addDays(self::followUpOffsetDays($count));

        return $natural->isPast() ? now()->addDays(30) : $natural;
    }

    /**
     * Listings whose next recurring "did it sell?" reminder is due now. Recurs
     * J+7 / J+17 / J+32 / then every 30 days for as long as the listing stays
     * `published`. Only a "sold" answer (or leaving `published`) stops it — a
     * "not sold" answer just re-arms the 30-day cycle.
     */
    public function scopeDueForFollowUp($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(fn ($q) => $q->whereNull('follow_up_response')->orWhere('follow_up_response', '!=', 'sold'))
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now());
    }

    /**
     * @deprecated use scopeDueForFollowUp — kept for callers that pre-date the
     * recurring cadence.
     */
    public function scopeNeedsFollowUp($query)
    {
        return $this->scopeDueForFollowUp($query);
    }

    // ===========================
    // MÉTHODES EXISTANTES
    // ===========================

    public function canBePublished()
    {
        return $this->status === 'draft' &&
            $this->payments()->where('payment_status', 'completed')->exists();
    }

    // ===========================
    // ✅ NOUVELLES MÉTHODES POUR L'ÉDITION
    // ===========================

    /**
     * Check if listing can be edited
     *
     * @return bool
     */
    public function canBeEdited(): bool
    {
        return $this->status === 'published' && $this->edit_count < 1;
    }

    /**
     * Get remaining edits count
     *
     * @return int
     */
    public function getRemainingEdits(): int
    {
        return max(0, 1 - $this->edit_count);
    }

    /**
     * Check if listing has been edited at least once
     *
     * @return bool
     */
    public function getHasBeenEditedAttribute(): bool
    {
        return $this->edit_count > 0;
    }

    /**
     * Increment edit count and update last_edited_at
     *
     * @return void
     */
    public function incrementEditCount(): void
    {
        $this->edit_count = $this->edit_count + 1;
        $this->last_edited_at = now();
        $this->save();
    }

    /**
     * Get edit status information
     *
     * @return array
     */
    public function getEditStatus(): array
    {
        return [
            'can_edit' => $this->canBeEdited(),
            'edit_count' => $this->edit_count,
            'max_edits_allowed' => 1,
            'edits_remaining' => $this->getRemainingEdits(),
            'has_been_edited' => $this->has_been_edited,
            'last_edited_at' => $this->last_edited_at ? $this->last_edited_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
