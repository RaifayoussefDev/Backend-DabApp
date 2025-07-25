<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes ,Notifiable;


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
        'password',
        'remember_token',
        'token',
        'token_expiration',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function bankCards()
    {
        return $this->hasMany(BankCard::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'seller_id');
    }

    public function auctionHistoriesAsSeller()
    {
        return $this->hasMany(AuctionHistory::class, 'seller_id');
    }

    public function auctionHistoriesAsBuyer()
    {
        return $this->hasMany(AuctionHistory::class, 'buyer_id');
    }
}
