<?php

namespace App\Traits;

use App\Models\Assist\HelperProfile;
use App\Models\Assist\AssistanceRequest;
use App\Models\Assist\AssistNotification;
use App\Models\Assist\Motorcycle as AssistMotorcycle;
use App\Models\Assist\Rating as AssistRating;

trait HasAssistRelations
{
    public function helperProfile()
    {
        return $this->hasOne(HelperProfile::class);
    }

    public function assistanceRequestsAsSeeker()
    {
        return $this->hasMany(AssistanceRequest::class, 'seeker_id');
    }

    public function assistanceRequestsAsHelper()
    {
        return $this->hasMany(AssistanceRequest::class, 'helper_id');
    }

    public function assistMotorcycles()
    {
        return $this->hasMany(AssistMotorcycle::class);
    }

    public function assistNotifications()
    {
        return $this->hasMany(AssistNotification::class);
    }

    public function ratingsReceived()
    {
        return $this->hasMany(AssistRating::class, 'rated_id');
    }

    public function ratingsGiven()
    {
        return $this->hasMany(AssistRating::class, 'rater_id');
    }

    public function isHelper(): bool
    {
        return $this->helperProfile()->exists();
    }

    public function isVerifiedHelper(): bool
    {
        return $this->helperProfile()->where('is_verified', true)->exists();
    }
}
