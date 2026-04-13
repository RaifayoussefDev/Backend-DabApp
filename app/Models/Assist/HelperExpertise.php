<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelperExpertise extends Model
{
    use HasFactory;

    protected $fillable = ['helper_profile_id', 'expertise_type_id'];

    // ── Relations ────────────────────────────────────────────────────────────

    public function helperProfile()
    {
        return $this->belongsTo(HelperProfile::class, 'helper_profile_id');
    }

    public function expertiseType()
    {
        return $this->belongsTo(ExpertiseType::class, 'expertise_type_id');
    }
}
