<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'birthday',
        'gender',
        'profile_picture',
        'address',
        'postal_code',
        'verified',
        'is_active',
        'is_online',
        'last_login',
        'token',
        'token_expiration',
        'role_id',
        'language',
        'timezone',
        'two_factor_enabled',
        'country_id',
    ];

    protected $hidden = [
        'password', 'remember_token', 'token', 'token_expiration',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
