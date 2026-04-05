<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanBillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'cycle_type',
        'duration_in_days',
        'display_name',
    ];

    /**
     * Get the plan this billing cycle belongs to
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get all prices for this cycle
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * Get all subscriptions using this cycle
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get price for a specific currency
     */
    public function priceForCurrency(string $currency): ?PlanPrice
    {
        return $this->prices()
            ->where('currency', strtoupper($currency))
            ->where('is_active', true)
            ->first();
    }
}
