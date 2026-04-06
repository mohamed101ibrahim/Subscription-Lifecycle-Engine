<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionState;
use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\Subscription;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Services\SubscriptionService;
use App\Services\SubscriptionStateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private SubscriptionStateService $stateService
    ) {}

    /**
     * List user's subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);

        $subscriptions = $this->subscriptionService->getUserSubscriptions($user, $page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions->items(),
            'pagination' => [
                'current_page' => $subscriptions->currentPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
                'last_page' => $subscriptions->lastPage(),
                'from' => $subscriptions->firstItem(),
                'to' => $subscriptions->lastItem(),
            ],
        ]);
    }

    /**
     * Create a new subscription
     */
    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $user = Auth::user();
        $plan = Plan::findOrFail($request->input('plan_id'));
        $cycle = PlanBillingCycle::findOrFail($request->input('plan_billing_cycle_id'));

        // Validate that the cycle belongs to the plan (additional check)
        if ($cycle->plan_id !== $plan->id) {
            return response()->json([
                'success' => false,
                'message' => 'Billing cycle does not belong to the specified plan',
            ], 400);
        }

        try {
            $withTrial = $request->has('trial_period_days') && $request->input('trial_period_days') > 0;
            $trialDays = $request->input('trial_period_days', 14);

            $subscription = $this->subscriptionService->createSubscription(
                $user,
                $plan,
                $cycle,
                $request->input('currency'),
                $withTrial,
                $trialDays
            );

            $details = $this->subscriptionService->getSubscriptionDetails($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => $details,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription details
     */
    public function show(Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        $details = $this->subscriptionService->getSubscriptionDetails($subscription);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Update subscription (change plan or billing cycle)
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        $validated = $request->validate([
            'plan_id' => 'sometimes|exists:plans,id',
            'plan_billing_cycle_id' => 'sometimes|exists:plan_billing_cycles,id',
            'currency' => 'sometimes|string|size:3|uppercase',
        ]);

        try {
            $updatedSubscription = $subscription;

            // Handle plan change
            if (isset($validated['plan_id']) || isset($validated['plan_billing_cycle_id'])) {
                $newPlan = isset($validated['plan_id'])
                    ? Plan::findOrFail($validated['plan_id'])
                    : $subscription->plan;

                $newCycle = isset($validated['plan_billing_cycle_id'])
                    ? PlanBillingCycle::findOrFail($validated['plan_billing_cycle_id'])
                    : $subscription->billingCycle;

                // Validate cycle belongs to plan
                if ($newCycle->plan_id !== $newPlan->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Billing cycle does not belong to the specified plan',
                    ], 400);
                }

                $updatedSubscription = $this->subscriptionService->changeSubscriptionPlan(
                    $subscription,
                    $newPlan,
                    $newCycle
                );
            }

            // Handle currency change (if supported in the future)
            // This would require finding new pricing for the same plan/cycle

            $details = $this->subscriptionService->getSubscriptionDetails($updatedSubscription);

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully',
                'data' => $details,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function destroy(Request $request, Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
            'immediate' => 'boolean', // Future: allow immediate cancellation
        ]);

        try {
            $reason = $validated['reason'] ?? 'User requested cancellation';

            $cancelledSubscription = $this->subscriptionService->cancelSubscription($subscription, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'data' => [
                    'id' => $cancelledSubscription->id,
                    'status' => $cancelledSubscription->status->value,
                    'canceled_at' => $cancelledSubscription->canceled_at?->toISOString(),
                    'cancellation_reason' => $cancelledSubscription->cancellation_reason,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel subscription (explicit endpoint)
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        return $this->destroy($request, $subscription);
    }

    /**
     * Get subscription history
     */
    public function history(Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        $history = $this->subscriptionService->listSubscriptionHistory($subscription);

        return response()->json([
            'success' => true,
            'data' => $history->map(function ($record) {
                return [
                    'id' => $record->id,
                    'previous_status' => $record->previous_status?->value,
                    'new_status' => $record->new_status->value,
                    'reason' => $record->reason,
                    'metadata' => $record->metadata,
                    'created_at' => $record->created_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Retry failed payment
     */
    public function retryPayment(Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        if ($subscription->status !== SubscriptionState::PAST_DUE) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not in past due status',
            ], 400);
        }

        try {
            // Get the latest failed payment
            $latestFailedPayment = $subscription->failedPayments()
                ->where('recovered', false)
                ->latest('failed_at')
                ->first();

            if (!$latestFailedPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed payment found to retry',
                ], 404);
            }

            // For now, we'll simulate the retry
            // In production, this would trigger actual payment processing
            $success = rand(1, 10) <= 7; // 70% success rate for demo

            if ($success) {
                // Simulate successful payment
                $recoveredSubscription = $this->stateService->recover($subscription);

                // Mark the failed payment as recovered
                $latestFailedPayment->update([
                    'recovered' => true,
                    'recovered_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment retry successful',
                    'data' => [
                        'subscription_status' => $recoveredSubscription->status->value,
                        'recovered_at' => $latestFailedPayment->recovered_at->toISOString(),
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment retry failed',
                ], 402); // Payment required
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change billing cycle
     */
    public function changeBillingCycle(Request $request, Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        $validated = $request->validate([
            'plan_billing_cycle_id' => 'required|exists:plan_billing_cycles,id',
        ]);

        try {
            $newCycle = PlanBillingCycle::findOrFail($validated['plan_billing_cycle_id']);

            // Validate cycle belongs to current plan
            if ($newCycle->plan_id !== $subscription->plan_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Billing cycle does not belong to the current plan',
                ], 400);
            }

            $updatedSubscription = $this->subscriptionService->changeSubscriptionCycle(
                $subscription,
                $newCycle
            );

            $details = $this->subscriptionService->getSubscriptionDetails($updatedSubscription);

            return response()->json([
                'success' => true,
                'message' => 'Billing cycle changed successfully',
                'data' => $details,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change billing cycle',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription status info with transition capabilities
     */
    public function statusInfo(Subscription $subscription): JsonResponse
    {
        // Ensure user owns this subscription
        if ($subscription->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to subscription',
            ], 403);
        }

        try {
            $statusInfo = $this->stateService->getStatusSummary($subscription);

            return response()->json([
                'success' => true,
                'data' => $statusInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get subscription status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}