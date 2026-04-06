<?php

namespace App\Services;

use App\Enums\SubscriptionState;
use App\Models\FailedPayment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private SubscriptionStateService $stateService,
        private GracePeriodService $gracePeriodService
    ) {}

    /**
     * Handle successful payment webhook
     */
    public function handlePaymentSuccess(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $oldStatus = $subscription->status;

            if ($subscription->status === SubscriptionState::PAST_DUE) {
                // Recover from past due
                return $this->stateService->recover($subscription);
            } elseif ($subscription->status === SubscriptionState::TRIALING) {
                // This shouldn't happen, but handle it
                Log::warning("Received payment success for trialing subscription {$subscription->id}");
                return $subscription;
            } elseif ($subscription->status === SubscriptionState::ACTIVE) {
                // Extend the subscription period
                return $this->extendSubscriptionPeriod($subscription);
            } elseif ($subscription->status === SubscriptionState::CANCELED) {
                // This shouldn't happen for canceled subscriptions
                Log::warning("Received payment success for canceled subscription {$subscription->id}");
                return $subscription;
            }

            return $subscription;
        });
    }

    /**
     * Handle failed payment webhook
     */
    public function handlePaymentFailure(
        Subscription $subscription,
        string $errorReason,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): Subscription {
        return DB::transaction(function () use ($subscription, $errorReason, $errorCode, $errorMessage) {

            $this->recordFailedPayment($subscription, $subscription->price->price, $subscription->price->currency, $errorReason, $errorCode, $errorMessage);

            // Only mark as past due if currently active
            if ($subscription->status === SubscriptionState::ACTIVE) {
                return $this->stateService->markPastDue($subscription, 'Payment failed: ' . $errorReason);
            }

            // Log but don't change status for other states
            Log::info("Payment failure for non-active subscription {$subscription->id}", [
                'status' => $subscription->status->value,
                'reason' => $errorReason,
            ]);

            return $subscription;
        });
    }

    /**
     * Record a failed payment attempt
     */
    public function recordFailedPayment(
        Subscription $subscription,
        float $amount,
        string $currency,
        string $reason,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): FailedPayment {
        return FailedPayment::create([
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'failure_reason' => $reason,
            'provider_error_code' => $errorCode,
            'provider_error_message' => $errorMessage,
            'failed_at' => Carbon::now('UTC'),
            'recovered' => false,
        ]);
    }

    /**
     * Retry a failed payment
     */
    public function retryFailedPayment(FailedPayment $failedPayment): bool
    {
        // This would integrate with your payment provider's API
        // For now, we'll simulate a retry

        try {
            $subscription = $failedPayment->subscription;

            // Simulate payment processing
            $success = $this->simulatePaymentRetry($subscription);

            if ($success) {
                $failedPayment->update([
                    'recovered' => true,
                    'recovered_at' => Carbon::now('UTC'),
                ]);


                $this->handlePaymentSuccess($subscription);

                Log::info("Payment retry successful for subscription {$subscription->id}");
                return true;
            } else {
                $this->recordFailedPayment(
                    $subscription,
                    $failedPayment->amount,
                    $failedPayment->currency,
                    'Retry failed',
                    'RETRY_FAILED',
                    'Payment retry was declined'
                );

                Log::warning("Payment retry failed for subscription {$subscription->id}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Payment retry error for failed payment {$failedPayment->id}", [
                'error' => $e->getMessage(),
                'subscription_id' => $failedPayment->subscription_id,
            ]);
            return false;
        }
    }

    /**
     * Get failed payments for a subscription
     */
    public function getFailedPayments(Subscription $subscription, int $limit = 10): Collection
    {
        return $subscription->failedPayments()
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats(): array
    {
        $thirtyDaysAgo = Carbon::now('UTC')->subDays(30);

        return [
            'total_failed_payments_30d' => FailedPayment::where('failed_at', '>=', $thirtyDaysAgo)->count(),
            'recovered_payments_30d' => FailedPayment::where('recovered', true)
                ->where('recovered_at', '>=', $thirtyDaysAgo)
                ->count(),
            'recovery_rate_30d' => $this->calculateRecoveryRate($thirtyDaysAgo),
            'failed_payments_by_reason' => FailedPayment::select('failure_reason', DB::raw('count(*) as count'))
                ->where('failed_at', '>=', $thirtyDaysAgo)
                ->groupBy('failure_reason')
                ->orderBy('count', 'desc')
                ->get(),
            'subscriptions_in_past_due' => Subscription::where('status', SubscriptionState::PAST_DUE)->count(),
        ];
    }

    /**
     * Process pending payment retries
     */
    public function processPendingRetries(): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Get failed payments that haven't been recovered and are within retry window
        $retryCandidates = FailedPayment::where('recovered', false)
            ->where('failed_at', '>=', Carbon::now('UTC')->subDays(7)) // Retry for up to 7 days
            ->whereDoesntHave('subscription', function ($query) {
                $query->where('status', SubscriptionState::CANCELED);
            })
            ->with('subscription')
            ->get();

        foreach ($retryCandidates as $failedPayment) {
            try {
                $success = $this->retryFailedPayment($failedPayment);
                $results['processed']++;

                if ($success) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'failed_payment_id' => $failedPayment->id,
                    'subscription_id' => $failedPayment->subscription_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Extend subscription period after successful payment
     */
    private function extendSubscriptionPeriod(Subscription $subscription): Subscription
    {
        $newPeriodEnd = $subscription->current_period_end->copy()->addDays($subscription->billingCycle->duration_in_days);

        $subscription->update([
            'current_period_start' => $subscription->current_period_end,
            'current_period_end' => $newPeriodEnd,
        ]);

        return $subscription->fresh();
    }

    /**
     * Calculate recovery rate for failed payments
     */
    private function calculateRecoveryRate(Carbon $since): float
    {
        $totalFailed = FailedPayment::where('failed_at', '>=', $since)->count();

        if ($totalFailed === 0) {
            return 0.0;
        }

        $recovered = FailedPayment::where('recovered', true)
            ->where('recovered_at', '>=', $since)
            ->count();

        return round(($recovered / $totalFailed) * 100, 2);
    }

    /**
     * Simulate payment retry (replace with actual payment provider integration)
     */
    private function simulatePaymentRetry(Subscription $subscription): bool
    {
        return rand(1, 10) <= 7;
    }

    /**
     * Validate webhook signature (implement based on your payment provider)
     */
    public function validateWebhookSignature(array $payload, string $signature, string $secret): bool
    {
        return true;
    }
}
