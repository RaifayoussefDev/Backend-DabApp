<?php

namespace App\Models\Marketplace;

use App\Models\CommissionSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPayout extends MarketplaceModel
{
    protected $table = 'marketplace_vendor_payouts';

    protected $fillable = [
        'vendor_id', 'commission_id', 'period_start', 'period_end',
        'gross_sales', 'commission', 'net_amount', 'currency', 'status',
        'approved_by', 'approved_at', 'paid_at',
        'bank_name', 'iban', 'transfer_ref', 'transfer_proof', 'items_snapshot', 'notes',
    ];

    protected $casts = [
        'period_start'   => 'date',
        'period_end'     => 'date',
        'gross_sales'    => 'decimal:2',
        'commission'     => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'approved_at'    => 'datetime',
        'paid_at'        => 'datetime',
        'items_snapshot' => 'array',
    ];

    protected $appends = ['transfer_proof_url'];

    public function getTransferProofUrlAttribute(): ?string
    {
        return $this->storageUrl($this->transfer_proof);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function commissionSetting(): BelongsTo
    {
        return $this->belongsTo(CommissionSetting::class, 'commission_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'payout_id');
    }
}
