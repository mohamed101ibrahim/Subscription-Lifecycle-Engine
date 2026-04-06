<?php

namespace App\Services;

use App\Enums\SubscriptionState;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionStateService
{
    public function __construct(
        private GracePeriodService $gracePeriodService
    ) {}

    /**
     * Activate a subscription (trial ended or payment recovered)
     */
    public function activate(Subscription $subscription, string $reason = 'Trial ended'): Subscription
    {
        return $this->transitionTo($subscription, SubscriptionState::ACTIVE, $reason);
    }

    /**
     * Mark subscription as past due (payment failed)
     */
    public function markPastDue(Subscription $subscription, string $reason): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason) {
            // Change status first
            $subscription = $this->transitionTo($subscription, SubscriptionState::PAST_DUE, $reason);
            $this->gracePeriodService->startGracePeriod($subscription);

            return $subscription;
        });
    }

    /**
     * Recover subscription from past due (payment succeeded during grace period)
     */
    public function recover(Subscription $subscription): Subscription
    {
        if (!$this->gracePeriodService->isInGracePeriod($subscription)) {
            throw new \InvalidArgumentException('Cannot recover subscription: grace period has expired');
        }

        return DB::transaction(function () use ($subscription) {
            // End grace period
            $this->gracePeriodService->endGracePeriod($subscription);

            // Reset billing period
            $subscription->update([
                'current_period_end' => $subscription->current_period_start->copy()->addDays($subscription->billingCycle->duration_in_days),
            ]);

            return $this->transitionTo($subscription, SubscriptionState::ACTIVE, 'Payment recovered during grace period');
        });
    }

    /**
     * Cancel a subscription
     */
    public function cancel(Subscription $subscription, string $reason = 'User requested'): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason) {
            $subscription->update([
                'canceled_at' => Carbon::now('UTC'),
                'cancellation_reason' => $reason,
                'ends_at' => $subscription->current_period_end,
            ]);

            return $this->transitionTo($subscription, SubscriptionState::CANCELED, $reason);
        });
    }

    /**
     * Check if a transition to a new state is valid
     */
    public function canTransitionTo(Subscription $subscription, SubscriptionState $newState): bool
    {
        return $subscription->status->canTransitionTo($newState);
    }

    /**
     * Get all valid transitions from current state
     */
    public function getValidTransitions(Subscription $subscription): array
    {
        return $subscription->status->validTransitionsTo();
    }

    /**
     * Force transition (admin override) - use with caution
     */
    public function forceTransitionTo(Subscription $subscription, SubscriptionState $newState, string $reason): Subscription
    {
        return $this->transitionTo($subscription, $newState, $reason, true);
    }

    /**
     * Internal method to handle state transitions
     */
    private function transitionTo(Subscription $subscription, SubscriptionState $newState, string $reason, bool $force = false): Subscription
    {
        if (!$force && !$this->canTransitionTo($subscription, $newState)) {
            throw new \InvalidArgumentException(
                "Invalid transition from {$subscription->status->value} to {$newState->value}"
            );
        }

        return DB::transaction(function () use ($subscription, $newState, $reason) {
            $oldStatus = $subscription->status;

            $subscription->update([
                'status' => $newState,
                'updated_at' => Carbon::now('UTC'),
            ]);

            // Record the transition
            $this->recordTransition($subscription, $oldStatus, $newState, $reason);

            return $subscription->fresh();
        });
    }

    /**
     * Record a state transition in history
     */
    private function recordTransition(Subscription $subscription, SubscriptionState $oldStatus, SubscriptionState $newStatus, string $reason): SubscriptionHistory
    {
        return SubscriptionHistory::create([
            'subscription_id' => $subscription->id,
            'previous_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'reason' => $reason,
            'metadata' => [
                'transition_type' => $this->getTransitionType($oldStatus, $newStatus),
                'timestamp' => Carbon::now('UTC')->toISOString(),
                'automated' => $this->isAutomatedTransition($reason),
            ],
        ]);
    }

    /**
     * Get transition type for metadata
     */
    private function getTransitionType(SubscriptionState $from, SubscriptionState $to): string
    {
        return match ([$from, $to]) {
            [SubscriptionState::TRIALING, SubscriptionState::ACTIVE] => 'trial_activation',
            [SubscriptionState::TRIALING, SubscriptionState::CANCELED] => 'trial_cancellation',
            [SubscriptionState::ACTIVE, SubscriptionState::PAST_DUE] => 'payment_failure',
            [SubscriptionState::ACTIVE, SubscriptionState::CANCELED] => 'user_cancellation',
            [SubscriptionState::PAST_DUE, SubscriptionState::ACTIVE] => 'payment_recovery',
            [SubscriptionState::PAST_DUE, SubscriptionState::CANCELED] => 'grace_period_expiry',
            default => 'manual_transition',
        };
    }

    /**
     * Check if transition was automated
     */
    private function isAutomatedTransition(string $reason): bool
    {
        $automatedReasons = [
            'Trial ended',
            'Grace period expired',
            'Payment failed',
            'Payment recovered during grace period',
        ];

        return in_array($reason, $automatedReasons);
    }

    /**
     * Get subscription status summary
     */
    public function getStatusSummary(Subscription $subscription): array
    {
        return [
            'current_status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'can_activate' => $this->canTransitionTo($subscription, SubscriptionState::ACTIVE),
            'can_mark_past_due' => $this->canTransitionTo($subscription, SubscriptionState::PAST_DUE),
            'can_recover' => $this->canTransitionTo($subscription, SubscriptionState::ACTIVE) && $this->gracePeriodService->isInGracePeriod($subscription),
            'can_cancel' => $this->canTransitionTo($subscription, SubscriptionState::CANCELED),
            'valid_transitions' => array_map(fn($state) => $state->value, $this->getValidTransitions($subscription)),
            'is_in_grace_period' => $this->gracePeriodService->isInGracePeriod($subscription),
            'grace_remaining_days' => $this->gracePeriodService->getGraceRemainingDays($subscription),
        ];
    }
}
