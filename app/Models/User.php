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
 *     description="User model representing a user in the system",
 *     required={"first_name", "last_name", "email", "role_id"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="User ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="first_name",
 *         type="string",
 *         description="User's first name",
 *         example="John"
 *     ),
 *     @OA\Property(
 *         property="last_name",
 *         type="string",
 *         description="User's last name",
 *         example="Doe"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         description="User's email address",
 *         example="john.doe@example.com"
 *     ),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         description="User's phone number",
 *         example="+212612345678",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="birthday",
 *         type="string",
 *         format="date",
 *         description="User's date of birth",
 *         example="1990-01-15",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="gender",
 *         type="string",
 *         description="User's gender",
 *         enum={"male", "female", "other"},
 *         example="male",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="profile_picture",
 *         type="string",
 *         description="URL or path to user's profile picture",
 *         example="profiles/user123.jpg",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         description="User's address",
 *         example="123 Main Street, Casablanca",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="postal_code",
 *         type="string",
 *         description="User's postal/ZIP code",
 *         example="20250",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="verified",
 *         type="boolean",
 *         description="Whether the user's account is verified",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether the user's account is active",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="is_online",
 *         type="boolean",
 *         description="Whether the user is currently online",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="last_login",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp of user's last login",
 *         example="2024-01-15T10:30:00Z",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="role_id",
 *         type="integer",
 *         description="ID of the user's role",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="country_id",
 *         type="integer",
 *         description="ID of the user's country",
 *         example=1,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="language",
 *         type="string",
 *         description="User's preferred language code",
 *         example="en",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="timezone",
 *         type="string",
 *         description="User's timezone",
 *         example="Africa/Casablanca",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="two_factor_enabled",
 *         type="boolean",
 *         description="Whether two-factor authentication is enabled",
 *         example=false
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the user was created",
 *         example="2024-01-15T10:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the user was last updated",
 *         example="2024-01-15T10:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="deleted_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp when the user was soft deleted (null if not deleted)",
 *         example=null,
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="role",
 *         ref="#/components/schemas/Role",
 *         description="User's role object"
 *     ),
 *     @OA\Property(
 *         property="country",
 *         ref="#/components/schemas/Country",
 *         description="User's country object"
 *     )
 * )
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'token',
        'token_expiration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birthday' => 'date',
        'verified' => 'boolean',
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'last_login' => 'datetime',
        'token_expiration' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Get the role that the user belongs to.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the country that the user belongs to.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get all bank cards for the user.
     */
    public function bankCards()
    {
        return $this->hasMany(BankCard::class);
    }

    /**
     * Get all wishlist items for the user.
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get all listings created by the user (as seller).
     */
    public function listings()
    {
        return $this->hasMany(Listing::class, 'seller_id');
    }

    /**
     * Get all auction histories where the user is the seller.
     */
    public function auctionHistoriesAsSeller()
    {
        return $this->hasMany(AuctionHistory::class, 'seller_id');
    }

    /**
     * Get all auction histories where the user is the buyer.
     */
    public function auctionHistoriesAsBuyer()
    {
        return $this->hasMany(AuctionHistory::class, 'buyer_id');
    }

    // ==========================================
    // PERMISSION METHODS
    // ==========================================

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permissionName
     * @return bool
     */
    public function hasPermission($permissionName)
    {
        // Admin role (role_id = 1) has all permissions
        if ($this->role_id === 1) {
            return true;
        }

        // Load role with permissions if not already loaded
        if (!$this->relationLoaded('role')) {
            $this->load('role.permissions');
        }

        // Check if role exists and has the permission
        if ($this->role) {
            return $this->role->permissions->contains('name', $permissionName);
        }

        return false;
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permissionName
     * @return bool
     */
    public function hasAnyPermission(array $permissions)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all permissions for the user.
     *
     * @return array
     */
    public function getPermissions()
    {
        // Admin role (role_id = 1) has all permissions
        if ($this->role_id === 1) {
            return Permission::pluck('name')->toArray();
        }

        // Load role with permissions if not already loaded
        if (!$this->relationLoaded('role')) {
            $this->load('role.permissions');
        }

        return $this->role ? $this->role->permissions->pluck('name')->toArray() : [];
    }

    /**
     * Get all permission objects for the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissionObjects()
    {
        // Admin role (role_id = 1) has all permissions
        if ($this->role_id === 1) {
            return Permission::all();
        }

        // Load role with permissions if not already loaded
        if (!$this->relationLoaded('role')) {
            $this->load('role.permissions');
        }

        return $this->role ? $this->role->permissions : collect();
    }

    // ==========================================
    // ACCESSOR & MUTATOR (OPTIONAL)
    // ==========================================

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Scope a query to only include active users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include verified users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope a query to only include online users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }
    /**
     * Get all permissions for the user (alias for getPermissions).
     * Used by AdminMenu system.
     *
     * @return array
     */
    public function getAllPermissions()
    {
        return $this->getPermissions();
    }
}
