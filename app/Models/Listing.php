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
    ];

    // ✅ AJOUT DES CASTS
    protected $casts = [
        'price' => 'decimal:2',
        'minimum_bid' => 'decimal:2',
        'auction_enabled' => 'boolean',
        'allow_submission' => 'boolean',
        'payment_pending' => 'boolean',
        'views_count' => 'integer',
        'edit_count' => 'integer',           // ✅ NOUVEAU
        'last_edited_at' => 'datetime',      // ✅ NOUVEAU
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
