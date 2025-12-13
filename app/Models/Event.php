<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'title_ar',
        'description_ar',
        'short_description_ar',
        'venue_name_ar',
        'address_ar',
        'interests_count',
        'featured_image',
        'category_id',
        'organizer_id',
        'event_date',
        'start_time',
        'end_time',
        'latitude',
        'longitude',
        'venue_name',
        'address',
        'city_id',
        'country_id',
        'max_participants',
        'registration_deadline',
        'price',
        'is_free',
        'status',
        'is_featured',
        'is_published',
        'views_count',
        'participants_count',
    ];

    protected $casts = [
        'event_date' => 'date',
        'registration_deadline' => 'datetime',
        'price' => 'decimal:2',
        'is_free' => 'boolean',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function images()
    {
        return $this->hasMany(EventImage::class);
    }

    public function participants()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function reviews()
    {
        return $this->hasMany(EventReview::class);
    }

    public function favorites()
    {
        return $this->hasMany(EventFavorite::class);
    }

    public function sponsors()
    {
        return $this->belongsToMany(EventSponsor::class, 'event_sponsor_relations', 'event_id', 'sponsor_id')
            ->withPivot('sponsorship_level')
            ->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(EventActivity::class)->orderBy('order_position');
    }

    public function tickets()
    {
        return $this->hasMany(EventTicket::class);
    }

    public function contacts()
    {
        return $this->hasMany(EventContact::class);
    }

    public function faqs()
    {
        return $this->hasMany(EventFaq::class)->orderBy('order_position');
    }

    public function updates()
    {
        return $this->hasMany(EventUpdate::class)->latest();
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
            ->where('event_date', '>=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', 1);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }

    public function isFull()
    {
        return $this->max_participants && $this->participants_count >= $this->max_participants;
    }

    public function isRegistrationOpen()
    {
        if ($this->status !== 'upcoming') {
            return false;
        }

        if ($this->registration_deadline && now()->gt($this->registration_deadline)) {
            return false;
        }

        return !$this->isFull();
    }
    public function interests()
   {
       return $this->hasMany(EventInterest::class);
   }

   public function interestedUsers()
   {
       return $this->belongsToMany(User::class, 'event_interests');
   }
   public function isInterestedBy(User $user): bool
   {
       return $this->interests()->where('user_id', $user->id)->exists();
   }
}
