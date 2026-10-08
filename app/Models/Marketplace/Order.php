<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends MarketplaceModel
{
    protected $table = 'marketplace_orders';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID            = 'paid';
    public const STATUS_PROCESSING      = 'processing';
    public const STATUS_SHIPPED         = 'shipped';
    public const STATUS_DELIVERED       = 'delivered';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_REFUNDED        = 'refunded';

    protected $fillable = [
        'order_number', 'user_id', 'address_id', 'shipping_address',
        'subtotal', 'shipping_fee', 'discount', 'total', 'currency',
        'status', 'idempotency_key', 'expires_at', 'paid_at', 'cancelled_at', 'cancel_reason', 'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'subtotal'         => 'decimal:2',
        'shipping_fee'     => 'decimal:2',
        'discount'         => 'decimal:2',
        'total'            => 'decimal:2',
        'expires_at'       => 'datetime',
        'paid_at'          => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    /** Human-readable, sortable, e.g. DAB-260921-0001 (the daily sequence restarts each day). */
    public static function generateOrderNumber(): string
    {
        $prefix = 'DAB-' . now()->format('ymd') . '-';

        $last = self::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function scopePendingPaymentExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(OrderPromoCode::class, 'order_id');
    }
}
