<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Handle payment success webhook
     */
    public function paymentSuccess(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subscription_id' => 'required|integer|exists:subscriptions,id',
                'transaction_id' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3|uppercase',
                'payment_method' => 'nullable|string|max:50',
                'processed_at' => 'nullable|date',
            ]);

            $subscription = Subscription::findOrFail($validated['subscription_id']);

            Log::info('Payment success webhook received', [
                'subscription_id' => $subscription->id,
                'transaction_id' => $validated['transaction_id'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'currency' => $validated['currency'] ?? null,
            ]);

            $updatedSubscription = $this->paymentService->handlePaymentSuccess($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Payment success processed',
                'data' => [
                    'subscription_id' => $updatedSubscription->id,
                    'status' => $updatedSubscription->status->value,
                    'current_period_end' => $updatedSubscription->current_period_end->toISOString(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Payment success webhook validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment success webhook processing failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $request->input('subscription_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle payment failure webhook
     */
    public function paymentFailed(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subscription_id' => 'required|integer|exists:subscriptions,id',
                'transaction_id' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3|uppercase',
                'failure_reason' => 'required|string|max:255',
                'error_code' => 'nullable|string|max:50',
                'error_message' => 'nullable|string|max:1000',
                'payment_method' => 'nullable|string|max:50',
                'failed_at' => 'nullable|date',
            ]);

            $subscription = Subscription::with('price')->findOrFail($validated['subscription_id']);

            Log::info('Payment failure webhook received', [
                'subscription_id' => $subscription->id,
                'failure_reason' => $validated['failure_reason'],
                'error_code' => $validated['error_code'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'currency' => $validated['currency'] ?? null,
            ]);

            $updatedSubscription = $this->paymentService->handlePaymentFailure(
                $subscription,
                $validated['failure_reason'],
                $validated['error_code'] ?? null,
                $validated['error_message'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment failure processed',
                'data' => [
                    'subscription_id' => $updatedSubscription->id,
                    'status' => $updatedSubscription->status->value,
                    'grace_period_ends_at' => $updatedSubscription->grace_period_ends_at?->toISOString(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Payment failure webhook validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment failure webhook processing failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $request->input('subscription_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle payment recovery webhook (when a failed payment is later recovered)
     */
    public function paymentRecovered(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subscription_id' => 'required|integer|exists:subscriptions,id',
                'original_transaction_id' => 'nullable|string|max:255',
                'recovery_transaction_id' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3|uppercase',
                'recovered_at' => 'nullable|date',
            ]);

            $subscription = Subscription::findOrFail($validated['subscription_id']);

            Log::info('Payment recovery webhook received', [
                'subscription_id' => $subscription->id,
                'original_transaction_id' => $validated['original_transaction_id'] ?? null,
                'recovery_transaction_id' => $validated['recovery_transaction_id'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'currency' => $validated['currency'] ?? null,
            ]);

            // For recovery, we treat it as a successful payment
            $updatedSubscription = $this->paymentService->handlePaymentSuccess($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Payment recovery processed',
                'data' => [
                    'subscription_id' => $updatedSubscription->id,
                    'status' => $updatedSubscription->status->value,
                    'current_period_end' => $updatedSubscription->current_period_end->toISOString(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Payment recovery webhook validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment recovery webhook processing failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $request->input('subscription_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle subscription cancellation webhook (from payment provider)
     */
    public function subscriptionCancelled(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subscription_id' => 'required|integer|exists:subscriptions,id',
                'cancellation_reason' => 'nullable|string|max:255',
                'cancelled_at' => 'nullable|date',
                'provider_cancellation_id' => 'nullable|string|max:255',
            ]);

            $subscription = Subscription::findOrFail($validated['subscription_id']);

            Log::info('Subscription cancellation webhook received', [
                'subscription_id' => $subscription->id,
                'cancellation_reason' => $validated['cancellation_reason'] ?? 'Provider initiated',
                'provider_cancellation_id' => $validated['provider_cancellation_id'] ?? null,
            ]);

            $reason = $validated['cancellation_reason'] ?? 'Provider initiated cancellation';

            // Use state service to cancel
            $stateService = app(\App\Services\SubscriptionStateService::class);
            $cancelledSubscription = $stateService->cancel($subscription, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancellation processed',
                'data' => [
                    'subscription_id' => $cancelledSubscription->id,
                    'status' => $cancelledSubscription->status->value,
                    'canceled_at' => $cancelledSubscription->canceled_at?->toISOString(),
                    'cancellation_reason' => $cancelledSubscription->cancellation_reason,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Subscription cancellation webhook validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Subscription cancellation webhook processing failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $request->input('subscription_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Generic webhook handler for payment providers
     * This can be customized based on your payment provider's webhook format
     */
    public function genericWebhook(Request $request, string $provider): JsonResponse
    {
        try {
            Log::info("Generic webhook received from provider: {$provider}", [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);

            // Here you would implement provider-specific webhook handling
            // For example, parse Stripe, PayPal, or other provider webhooks

            $payload = $request->all();

            // Basic validation - adjust based on provider
            if (!isset($payload['type']) || !isset($payload['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook format',
                ], 400);
            }

            $eventType = $payload['type'];
            $eventData = $payload['data'];

            // Route to appropriate handler based on event type
            switch ($eventType) {
                case 'payment.succeeded':
                case 'invoice.payment_succeeded':
                    return $this->handleGenericPaymentSuccess($eventData);

                case 'payment.failed':
                case 'invoice.payment_failed':
                    return $this->handleGenericPaymentFailure($eventData);

                case 'customer.subscription.deleted':
                    return $this->handleGenericSubscriptionCancelled($eventData);

                default:
                    Log::info("Unhandled webhook event type: {$eventType}");
                    return response()->json([
                        'success' => true,
                        'message' => 'Webhook received but not processed',
                        'event_type' => $eventType,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error("Generic webhook processing failed for provider {$provider}", [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle generic payment success (adapt to your provider's format)
     */
    private function handleGenericPaymentSuccess(array $eventData): JsonResponse
    {
        // Adapt this based on your payment provider's webhook format
        $subscriptionId = $eventData['subscription_id'] ?? null;

        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID not found in webhook data',
            ], 400);
        }

        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            Log::warning("Subscription not found for payment success", ['subscription_id' => $subscriptionId]);
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found',
            ], 404);
        }

        return $this->paymentSuccess(request()->merge(['subscription_id' => $subscriptionId]));
    }

    /**
     * Handle generic payment failure (adapt to your provider's format)
     */
    private function handleGenericPaymentFailure(array $eventData): JsonResponse
    {
        // Adapt this based on your payment provider's webhook format
        $subscriptionId = $eventData['subscription_id'] ?? null;
        $failureReason = $eventData['failure_reason'] ?? 'Payment failed';

        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID not found in webhook data',
            ], 400);
        }

        return $this->paymentFailed(request()->merge([
            'subscription_id' => $subscriptionId,
            'failure_reason' => $failureReason,
            'error_code' => $eventData['error_code'] ?? null,
            'error_message' => $eventData['error_message'] ?? null,
        ]));
    }

    /**
     * Handle generic subscription cancellation (adapt to your provider's format)
     */
    private function handleGenericSubscriptionCancelled(array $eventData): JsonResponse
    {
        // Adapt this based on your payment provider's webhook format
        $subscriptionId = $eventData['subscription_id'] ?? null;
        $reason = $eventData['cancellation_reason'] ?? 'Provider initiated';

        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID not found in webhook data',
            ], 400);
        }

        return $this->subscriptionCancelled(request()->merge([
            'subscription_id' => $subscriptionId,
            'cancellation_reason' => $reason,
        ]));
    }
}