<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerClick extends Model
{
    public $timestamps = false;

    protected $fillable = ['banner_id', 'user_id', 'ip_address', 'clicked_at'];

    protected $casts = ['clicked_at' => 'datetime'];

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
