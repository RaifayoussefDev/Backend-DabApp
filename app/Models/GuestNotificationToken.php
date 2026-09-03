<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Push token for a guest (not-logged-in) mobile device. Mirrors
 * App\Models\NotificationToken but keyed by the app's `device_id` instead of a
 * user.
 */
class GuestNotificationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'fcm_token',
        'device_type',
        'device_name',
        'device_model',
        'os_version',
        'app_version',
        'locale',
        'country_id',
        'city_id',
        'is_active',
        'last_active_at',
        'last_notified_at',
        'failed_attempts',
        'last_failed_at',
        'converted_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDeviceType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Apply an admin broadcast's guest audience filters. Used both for the
     * pre-send count (AdminNotificationController) and the actual send
     * (GuestMassNotificationJob) so the two never drift apart.
     *
     * @param array $f ['device_type','app_version','country_id','city_id','active_since','viewed_category_id','viewed_listing_id']
     */
    public function scopeMatchingFilters($query, array $f)
    {
        if (!empty($f['device_type']))  $query->where('device_type', $f['device_type']);
        if (!empty($f['app_version']))  $query->where('app_version', $f['app_version']);
        if (!empty($f['country_id']))   $query->where('country_id', $f['country_id']);
        if (!empty($f['city_id']))      $query->where('city_id', $f['city_id']);
        if (!empty($f['active_since'])) $query->where('last_active_at', '>=', $f['active_since']);

        if (!empty($f['viewed_listing_id']) || !empty($f['viewed_category_id'])) {
            $deviceIds = View::query()
                ->where('viewable_type', Listing::class)
                ->whereNotNull('device_id')
                ->when(!empty($f['viewed_listing_id']), fn ($q) => $q->where('viewable_id', $f['viewed_listing_id']))
                ->when(!empty($f['viewed_category_id']), fn ($q) => $q->whereIn(
                    'viewable_id',
                    Listing::where('category_id', $f['viewed_category_id'])->select('id')
                ))
                ->distinct()
                ->pluck('device_id');

            $query->whereIn('device_id', $deviceIds);
        }

        return $query;
    }

    // Methods
    public function markNotified(): void
    {
        $this->forceFill(['last_notified_at' => now()])->save();
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_attempts');
        $this->update(['last_failed_at' => now()]);

        // Same threshold as NotificationToken.
        if ($this->failed_attempts >= 5) {
            $this->deactivate();
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'last_failed_at' => null,
        ]);
    }
}
