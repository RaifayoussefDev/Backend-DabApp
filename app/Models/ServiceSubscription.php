<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ServiceSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'plan_id',
        'billing_cycle',
        'status',
        'current_period_start',
        'current_period_end',
        'next_billing_date',
        'trial_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'payment_method_id',
        'bank_card_id',
        'auto_renew',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'next_billing_date' => 'date',
        'trial_ends_at' => 'date',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    /**
     * Get the provider that owns the subscription
     */
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    /**
     * Get the plan for this subscription
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the payment method
     */
    public function paymentMethod()
    {
        return $this->belongsTo(CardType::class, 'payment_method_id');
    }

    /**
     * Get the bank card
     */
    public function bankCard()
    {
        return $this->belongsTo(BankCard::class, 'bank_card_id');
    }

    /**
     * Get all transactions for this subscription
     */
    public function transactions()
    {
        return $this->hasMany(SubscriptionTransaction::class, 'subscription_id');
    }

    /**
     * Scope: Active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Cancelled subscriptions
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope: Expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope: Trial subscriptions
     */
    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    /**
     * Scope: Due for renewal
     */
    public function scopeDueForRenewal($query)
    {
        return $query->where('status', 'active')
            ->where('next_billing_date', '<=', Carbon::now()->addDays(3))
            ->where('auto_renew', true);
    }

    /**
     * Check if subscription is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is on trial
     */
    public function onTrial()
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at && 
               Carbon::now()->lte($this->trial_ends_at);
    }

    /**
     * Check if subscription is cancelled
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->status === 'expired';
    }

    /**
     * Cancel the subscription
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);
    }

    /**
     * Reactivate the subscription
     */
    public function reactivate()
    {
        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'auto_renew' => true,
        ]);
    }

    /**
     * Get days until next billing
     */
    public function getDaysUntilRenewalAttribute()
    {
        if (!$this->next_billing_date) {
            return null;
        }

        return Carbon::now()->diffInDays($this->next_billing_date, false);
    }

    /**
     * Get current price based on billing cycle
     */
    public function getCurrentPriceAttribute()
    {
        if ($this->billing_cycle === 'yearly') {
            return $this->plan->price_yearly;
        }
        return $this->plan->price_monthly;
    }

    /**
     * Renew subscription
     */
    public function renew()
    {
        $interval = $this->billing_cycle === 'yearly' ? 12 : 1;
        
        $this->update([
            'current_period_start' => $this->current_period_end->addDay(),
            'current_period_end' => $this->current_period_end->addMonths($interval),
            'next_billing_date' => $this->current_period_end->addMonths($interval),
            'status' => 'active',
        ]);
    }
}