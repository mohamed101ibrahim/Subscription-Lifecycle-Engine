<?php

namespace App\Services;

use App\Enums\SubscriptionState;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GracePeriodService
{
    private const DEFAULT_GRACE_PERIOD_DAYS = 3;

    /**
     * Start grace period for a subscription
     */
    public function startGracePeriod(Subscription $subscription, int $daysUntilExpiry = self::DEFAULT_GRACE_PERIOD_DAYS): Subscription
    {
        if ($subscription->status !== SubscriptionState::PAST_DUE) {
            throw new \InvalidArgumentException('Can only start grace period for past due subscriptions');
        }

        $gracePeriodEndsAt = Carbon::now('UTC')->addDays($daysUntilExpiry);

        $subscription->update([
            'grace_period_ends_at' => $gracePeriodEndsAt,
        ]);

        return $subscription->fresh();
    }

    /**
     * Check if subscription is currently in grace period
     */
    public function isInGracePeriod(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionState::PAST_DUE) {
            return false;
        }

        if (!$subscription->grace_period_ends_at) {
            return false;
        }

        return Carbon::now('UTC')->isBefore($subscription->grace_period_ends_at);
    }

    /**
     * Get remaining days in grace period
     */
    public function getGraceRemainingDays(Subscription $subscription): int
    {
        if (!$this->isInGracePeriod($subscription)) {
            return 0;
        }

        return Carbon::now('UTC')->diffInDays($subscription->grace_period_ends_at, false);
    }

    /**
     * Get remaining hours in grace period
     */
    public function getGraceRemainingHours(Subscription $subscription): int
    {
        if (!$this->isInGracePeriod($subscription)) {
            return 0;
        }

        return Carbon::now('UTC')->diffInHours($subscription->grace_period_ends_at, false);
    }

    /**
     * Check if grace period has expired
     */
    public function hasGracePeriodExpired(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionState::PAST_DUE) {
            return false;
        }

        if (!$subscription->grace_period_ends_at) {
            return true; 
        }

        return Carbon::now('UTC')->isAfter($subscription->grace_period_ends_at);
    }

    /**
     * End grace period for a subscription
     */
    public function endGracePeriod(Subscription $subscription): Subscription
    {
        $subscription->update([
            'grace_period_ends_at' => null,
        ]);

        return $subscription->fresh();
    }

    /**
     * Extend grace period
     */
    public function extendGracePeriod(Subscription $subscription, int $additionalDays): Subscription
    {
        if (!$this->isInGracePeriod($subscription)) {
            throw new \InvalidArgumentException('Cannot extend grace period: subscription is not in grace period');
        }

        $newGracePeriodEndsAt = $subscription->grace_period_ends_at->copy()->addDays($additionalDays);

        $subscription->update([
            'grace_period_ends_at' => $newGracePeriodEndsAt,
        ]);

        return $subscription->fresh();
    }

    /**
     * Process expired grace periods (called by scheduler)
     */
    public function processExpiredGracePeriods(): Collection
    {
        return DB::transaction(function () {
            $expiredSubscriptions = Subscription::where('status', SubscriptionState::PAST_DUE)
                ->whereNotNull('grace_period_ends_at')
                ->where('grace_period_ends_at', '<=', Carbon::now('UTC'))
                ->where('updated_at', '<', Carbon::now('UTC')->subHour())
                ->lockForUpdate()
                ->get();

            $processedSubscriptions = collect();

            foreach ($expiredSubscriptions as $subscription) {
                try {
                    $stateService = app(SubscriptionStateService::class);
                    $canceledSubscription = $stateService->cancel($subscription, 'Grace period expired');

                    $processedSubscriptions->push($canceledSubscription);
                } catch (\Exception $e) {
                    \Log::error("Failed to cancel subscription {$subscription->id} after grace period expiry", [
                        'error' => $e->getMessage(),
                        'subscription_id' => $subscription->id,
                    ]);
                }
            }

            return $processedSubscriptions;
        });
    }

    /**
     * Get subscriptions approaching grace period expiry
     */
    public function getSubscriptionsApproachingExpiry(int $hoursUntilExpiry = 24): Collection
    {
        $expiryThreshold = Carbon::now('UTC')->addHours($hoursUntilExpiry);

        return Subscription::where('status', SubscriptionState::PAST_DUE)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', $expiryThreshold)
            ->where('grace_period_ends_at', '>', Carbon::now('UTC'))
            ->with(['user', 'plan'])
            ->get();
    }

    /**
     * Get grace period statistics
     */
    public function getGracePeriodStats(): array
    {
        $now = Carbon::now('UTC');

        return [
            'total_in_grace_period' => Subscription::where('status', SubscriptionState::PAST_DUE)
                ->whereNotNull('grace_period_ends_at')
                ->where('grace_period_ends_at', '>', $now)
                ->count(),

            'expiring_within_24h' => Subscription::where('status', SubscriptionState::PAST_DUE)
                ->whereNotNull('grace_period_ends_at')
                ->where('grace_period_ends_at', '>', $now)
                ->where('grace_period_ends_at', '<=', $now->copy()->addDay())
                ->count(),

            'expiring_within_1h' => Subscription::where('status', SubscriptionState::PAST_DUE)
                ->whereNotNull('grace_period_ends_at')
                ->where('grace_period_ends_at', '>', $now)
                ->where('grace_period_ends_at', '<=', $now->copy()->addHour())
                ->count(),

            'expired_unprocessed' => Subscription::where('status', SubscriptionState::PAST_DUE)
                ->whereNotNull('grace_period_ends_at')
                ->where('grace_period_ends_at', '<=', $now)
                ->count(),

            'average_grace_period_days' => self::DEFAULT_GRACE_PERIOD_DAYS,
        ];
    }

    /**
     * Send grace period warning notifications
     */
    public function sendGracePeriodWarnings(): void
    {
        $subscriptions = $this->getSubscriptionsApproachingExpiry(24);

        foreach ($subscriptions as $subscription) {

            \Log::info("Grace period warning for subscription {$subscription->id}", [
                'user_id' => $subscription->user_id,
                'plan_name' => $subscription->plan->name,
                'grace_period_ends_at' => $subscription->grace_period_ends_at->toISOString(),
                'remaining_hours' => $this->getGraceRemainingHours($subscription),
            ]);


        }
    }
}
