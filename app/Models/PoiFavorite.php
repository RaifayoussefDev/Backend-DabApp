<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoiFavorite extends Model
{
    protected $table = 'poi_favorites';

    // Disable updated_at since the migration only has created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'poi_id',
        'user_id',
    ];

    public function poi()
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
