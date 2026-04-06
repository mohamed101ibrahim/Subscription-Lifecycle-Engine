<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\PlanPrice;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class PlanService
{
    /**
     * Create a new subscription plan
     */
    public function createPlan(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            return Plan::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'features' => $data['features'] ?? [],
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Add a billing cycle to a plan
     */
    public function addBillingCycle(Plan $plan, string $cycleType, int $durationInDays, ?string $displayName = null): PlanBillingCycle
    {
        return DB::transaction(function () use ($plan, $cycleType, $durationInDays, $displayName) {
            return PlanBillingCycle::create([
                'plan_id' => $plan->id,
                'cycle_type' => $cycleType,
                'duration_in_days' => $durationInDays,
                'display_name' => $displayName ?? ucfirst($cycleType),
            ]);
        });
    }

    /**
     * Add pricing for a billing cycle
     */
    public function addPricing(PlanBillingCycle $cycle, string $currency, float $price): PlanPrice
    {
        return DB::transaction(function () use ($cycle, $currency, $price) {
            return PlanPrice::create([
                'plan_billing_cycle_id' => $cycle->id,
                'currency' => strtoupper($currency),
                'price' => $price,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Get pricing by currency and billing cycle for a plan
     */
    public function getPricingByCurrencyAndCycle(Plan $plan, string $currency, string $billingCycle): ?PlanPrice
    {
        return $plan->billingCycles()
            ->where('cycle_type', $billingCycle)
            ->first()
            ?->prices()
            ->where('currency', strtoupper($currency))
            ->where('is_active', true)
            ->first();
    }

    /**
     * List active plans with pagination
     */
    public function listActivePlans(int $page = 1, int $perPage = 15): Paginator
    {
        return Plan::where('is_active', true)
            ->with(['billingCycles.prices' => function ($query) {
                $query->where('is_active', true);
            }])
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get plan details with all pricing
     */
    public function getPlanDetails(Plan $plan): Plan
    {
        return $plan->load([
            'billingCycles.prices' => function ($query) {
                $query->where('is_active', true);
            }
        ]);
    }

    /**
     * Activate a plan
     */
    public function activatePlan(Plan $plan): Plan
    {
        $plan->update(['is_active' => true]);
        return $plan->fresh();
    }

    /**
     * Deactivate a plan
     */
    public function deactivatePlan(Plan $plan): Plan
    {
        $plan->update(['is_active' => false]);
        return $plan->fresh();
    }

    /**
     * Update plan information
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update([
                'name' => $data['name'] ?? $plan->name,
                'description' => $data['description'] ?? $plan->description,
                'features' => $data['features'] ?? $plan->features,
                'is_active' => $data['is_active'] ?? $plan->is_active,
            ]);

            return $plan->fresh();
        });
    }

    /**
     * Get all available currencies for a plan
     */
    public function getAvailableCurrencies(Plan $plan): SupportCollection
    {
        return $plan->billingCycles()
            ->join('plan_prices', 'plan_billing_cycles.id', '=', 'plan_prices.plan_billing_cycle_id')
            ->where('plan_prices.is_active', true)
            ->distinct()
            ->pluck('plan_prices.currency')
            ->sort();
    }

    /**
     * Get all available billing cycles for a plan
     */
    public function getAvailableBillingCycles(Plan $plan): Collection
    {
        return $plan->billingCycles()
            ->with(['prices' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();
    }
}