<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_billing_cycle_id',
        'currency',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the billing cycle this price belongs to
     */
    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(PlanBillingCycle::class);
    }

    /**
     * Get all subscriptions using this price
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope: Get only active prices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get prices for a specific currency
     */
    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    /**
     * Get formatted price string
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }
}
