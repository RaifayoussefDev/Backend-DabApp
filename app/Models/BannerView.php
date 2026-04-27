<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerView extends Model
{
    public $timestamps = false;

    protected $fillable = ['banner_id', 'user_id', 'ip_address', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
