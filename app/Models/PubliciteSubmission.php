<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PubliciteSubmission extends Model
{
    protected $table = 'publicite_submissions';

    protected $fillable = [
        'banner_id',
        'user_id',
        'nom',
        'prenom',
        'phone',
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
        return $this->belongsTo(\App\Models\City::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
