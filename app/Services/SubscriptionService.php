<?php

namespace App\Services;

use App\Enums\SubscriptionState;
use App\Models\FailedPayment;
use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        private SubscriptionStateService $stateService,
        private GracePeriodService $gracePeriodService
    ) {}

    /**
     * Create a new subscription for a user
     */
    public function createSubscription(
        User $user,
        Plan $plan,
        PlanBillingCycle $cycle,
        string $currency,
        bool $withTrial = false,
        int $trialDays = 14
    ): Subscription {
        return DB::transaction(function () use ($user, $plan, $cycle, $currency, $withTrial, $trialDays) {
            // Get the pricing
            $price = $this->getPricingByCurrencyAndCycle($plan, $currency, $cycle->cycle_type);
            if (!$price) {
                throw new \InvalidArgumentException("No pricing found for plan {$plan->name} with currency {$currency} and cycle {$cycle->cycle_type}");
            }

            $now = Carbon::now('UTC');
            $trialEndsAt = $withTrial ? $now->copy()->addDays($trialDays) : null;
            $currentPeriodEnd = $trialEndsAt ?? $now->copy()->addDays($cycle->duration_in_days);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_billing_cycle_id' => $cycle->id,
                'plan_price_id' => $price->id,
                'status' => $withTrial ? SubscriptionState::TRIALING : SubscriptionState::ACTIVE,
                'trial_ends_at' => $trialEndsAt,
                'started_at' => $now,
                'current_period_start' => $now,
                'current_period_end' => $currentPeriodEnd,
                'ends_at' => null,
            ]);

            // Record initial state in history
            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'previous_status' => null,
                'new_status' => $subscription->status->value,
                'reason' => $withTrial ? 'Subscription created with trial' : 'Subscription created',
                'metadata' => [
                    'plan_name' => $plan->name,
                    'billing_cycle' => $cycle->cycle_type,
                    'currency' => $currency,
                    'price' => $price->price,
                    'trial_days' => $withTrial ? $trialDays : 0,
                ],
            ]);

            return $subscription->load(['plan', 'billingCycle', 'price']);
        });
    }

    /**
     * Get user's subscriptions with pagination
     */
    public function getUserSubscriptions(User $user, int $page = 1, int $perPage = 15): Paginator
    {
        return $user->subscriptions()
            ->with(['plan', 'billingCycle', 'price'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get detailed subscription information
     */
    public function getSubscriptionDetails(Subscription $subscription): array
    {
        $subscription->load(['plan', 'billingCycle', 'price', 'histories' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        return [
            'id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'description' => $subscription->plan->description,
                'features' => $subscription->plan->features,
            ],
            'billing_cycle' => [
                'type' => $subscription->billingCycle->cycle_type,
                'display_name' => $subscription->billingCycle->display_name,
                'duration_days' => $subscription->billingCycle->duration_in_days,
            ],
            'pricing' => [
                'amount' => $subscription->price->price,
                'currency' => $subscription->price->currency,
                'formatted' => $this->formatPrice($subscription->price),
            ],
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'started_at' => $subscription->started_at->toISOString(),
            'current_period_start' => $subscription->current_period_start->toISOString(),
            'current_period_end' => $subscription->current_period_end->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString(),
            'canceled_at' => $subscription->canceled_at?->toISOString(),
            'cancellation_reason' => $subscription->cancellation_reason,
            'is_in_grace_period' => $this->gracePeriodService->isInGracePeriod($subscription),
            'grace_remaining_days' => $this->gracePeriodService->getGraceRemainingDays($subscription),
            'can_cancel' => $this->stateService->canTransitionTo($subscription, SubscriptionState::CANCELED),
            'can_change_plan' => in_array($subscription->status, [SubscriptionState::TRIALING, SubscriptionState::ACTIVE]),
            'recent_history' => $subscription->histories->map(function ($history) {
                return [
                    'id' => $history->id,
                    'previous_status' => $history->previous_status,
                    'new_status' => $history->new_status,
                    'reason' => $history->reason,
                    'metadata' => $history->metadata,
                    'created_at' => $history->created_at->toISOString(),
                ];
            }),
            'created_at' => $subscription->created_at->toISOString(),
            'updated_at' => $subscription->updated_at->toISOString(),
        ];
    }

    /**
     * Calculate next billing date for a subscription
     */
    public function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        return $subscription->current_period_end->copy();
    }

    /**
     * Change subscription plan
     */
    public function changeSubscriptionPlan(Subscription $subscription, Plan $newPlan, PlanBillingCycle $newCycle): Subscription
    {
        if (!in_array($subscription->status, [SubscriptionState::TRIALING, SubscriptionState::ACTIVE])) {
            throw new \InvalidArgumentException('Cannot change plan for subscription in current state');
        }

        return DB::transaction(function () use ($subscription, $newPlan, $newCycle) {
            $oldPlan = $subscription->plan->name;
            $oldCycle = $subscription->billingCycle->cycle_type;

            $newPrice = $this->getPricingByCurrencyAndCycle($newPlan, $subscription->price->currency, $newCycle->cycle_type);
            if (!$newPrice) {
                throw new \InvalidArgumentException("No pricing found for new plan with currency {$subscription->price->currency}");
            }

            $subscription->update([
                'plan_id' => $newPlan->id,
                'plan_billing_cycle_id' => $newCycle->id,
                'plan_price_id' => $newPrice->id,
            ]);

            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'previous_status' => $subscription->status->value,
                'new_status' => $subscription->status->value, // Status unchanged
                'reason' => 'Plan changed',
                'metadata' => [
                    'old_plan' => $oldPlan,
                    'new_plan' => $newPlan->name,
                    'old_cycle' => $oldCycle,
                    'new_cycle' => $newCycle->cycle_type,
                    'currency' => $subscription->price->currency,
                    'old_price' => $subscription->price->price,
                    'new_price' => $newPrice->price,
                ],
            ]);

            return $subscription->fresh(['plan', 'billingCycle', 'price']);
        });
    }

    /**
     * Change subscription billing cycle
     */
    public function changeSubscriptionCycle(Subscription $subscription, PlanBillingCycle $newCycle): Subscription
    {
        if (!in_array($subscription->status, [SubscriptionState::TRIALING, SubscriptionState::ACTIVE])) {
            throw new \InvalidArgumentException('Cannot change billing cycle for subscription in current state');
        }

        return DB::transaction(function () use ($subscription, $newCycle) {
            // Get pricing for new cycle with same currency
            $newPrice = $this->getPricingByCurrencyAndCycle($subscription->plan, $subscription->price->currency, $newCycle->cycle_type);
            if (!$newPrice) {
                throw new \InvalidArgumentException("No pricing found for cycle {$newCycle->cycle_type} with currency {$subscription->price->currency}");
            }

            $oldCycle = $subscription->billingCycle->cycle_type;

            $subscription->update([
                'plan_billing_cycle_id' => $newCycle->id,
                'plan_price_id' => $newPrice->id,
                'current_period_end' => $subscription->current_period_start->copy()->addDays($newCycle->duration_in_days),
            ]);

            // Record the change
            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'previous_status' => $subscription->status->value,
                'new_status' => $subscription->status->value,
                'reason' => 'Billing cycle changed',
                'metadata' => [
                    'old_cycle' => $oldCycle,
                    'new_cycle' => $newCycle->cycle_type,
                    'currency' => $subscription->price->currency,
                    'old_price' => $subscription->price->price,
                    'new_price' => $newPrice->price,
                    'period_end_updated' => true,
                ],
            ]);

            return $subscription->fresh(['plan', 'billingCycle', 'price']);
        });
    }

    /**
     * Get subscription history
     */
    public function listSubscriptionHistory(Subscription $subscription): Collection
    {
        return $subscription->histories()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription, string $reason = 'User requested'): Subscription
    {
        return $this->stateService->cancel($subscription, $reason);
    }

    /**
     * Get pricing helper method
     */
    private function getPricingByCurrencyAndCycle(Plan $plan, string $currency, string $billingCycle): ?PlanPrice
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
     * Format price for display
     */
    private function formatPrice(PlanPrice $price): string
    {
        $symbol = match (strtoupper($price->currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'د.إ',
            'EGP' => 'ج.م',
            default => $price->currency . ' ',
        };

        return $symbol . number_format($price->price, 2);
    }
}
