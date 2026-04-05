<?php

namespace App\Models;

use App\Enums\SubscriptionState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_billing_cycle_id',
        'plan_price_id',
        'status',
        'trial_ends_at',
        'started_at',
        'ends_at',
        'current_period_start',
        'current_period_end',
        'grace_period_ends_at',
        'canceled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'status' => SubscriptionState::class,
        'trial_ends_at' => 'datetime',
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * Get the user this subscription belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan this subscription uses
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the billing cycle this subscription uses
     */
    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(PlanBillingCycle::class, 'plan_billing_cycle_id');
    }

    /**
     * Get the price this subscription uses
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    /**
     * Get all history records for this subscription
     */
    public function histories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class)->orderByDesc('created_at');
    }

    /**
     * Get all failed payments for this subscription
     */
    public function failedPayments(): HasMany
    {
        return $this->hasMany(FailedPayment::class)->orderByDesc('failed_at');
    }

    /**
     * Check if subscription is in trial
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is past due
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * Check if subscription is in grace period
     */
    public function isInGracePeriod(): bool
    {
        return $this->status === 'past_due' && $this->grace_period_ends_at?->isFuture();
    }

    /**
     * Get remaining grace period days
     */
    public function getGracePeriodRemainingDays(): int
    {
        if (!$this->isInGracePeriod()) {
            return 0;
        }
        return (int) $this->grace_period_ends_at->diffInDays(now());
    }

    /**
     * Scope: Get only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Get only trialing subscriptions
     */
    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    /**
     * Scope: Get only past due subscriptions
     */
    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    /**
     * Scope: Get only canceled subscriptions
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    /**
     * Scope: Get subscriptions for a user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get subscriptions with expired trials
     */
    public function scopeWithExpiredTrials($query)
    {
        return $query->where('status', 'trialing')
            ->where('trial_ends_at', '<=', now())
            ->where('updated_at', '<', now()->subHour());
    }

    /**
     * Scope: Get subscriptions with expired grace periods
     */
    public function scopeWithExpiredGracePeriods($query)
    {
        return $query->where('status', 'past_due')
            ->where('grace_period_ends_at', '<=', now())
            ->where('updated_at', '<', now()->subHour());
    }
}
