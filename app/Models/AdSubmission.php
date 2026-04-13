<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSubmission extends Model
{
    protected $table = 'ad_submissions';

    protected $fillable = [
        'banner_id',
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'city_id',
        'synced_to_sheet',
    ];

    protected $casts = [
        'synced_to_sheet' => 'boolean',
    ];

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
