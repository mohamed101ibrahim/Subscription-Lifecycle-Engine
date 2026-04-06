<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Http\Resources\SuccessResponse;
use App\Http\Resources\ErrorResponse;

class PlanController extends Controller
{
    public function __construct(
        private PlanService $planService
    ) {}

    /**
     * List all active plans
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);

        $plans = $this->planService->listActivePlans($page, $perPage);

        return response()->json(
            SuccessResponse::collection(
                $plans->items(),
                'Plans retrieved successfully',
                [
                    'current_page' => $plans->currentPage(),
                    'per_page' => $plans->perPage(),
                    'total' => $plans->total(),
                    'last_page' => $plans->lastPage(),
                    'from' => $plans->firstItem(),
                    'to' => $plans->lastItem(),
                ]
            )
        );
    }

    /**
     * Create a new plan
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        // Additional check for duplicate name (belt and suspenders)
        if (Plan::where('name', $request->name)->exists()) {
            return response()->json(
                ErrorResponse::make(
                    'A plan with this name already exists',
                    ['name' => ['The name has already been taken.']],
                    'PLAN_NAME_EXISTS',
                    422
                ),
                422
            );
        }

        try {
            $plan = $this->planService->createPlan($request->validated());

            return response()->json(
                SuccessResponse::created([
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'features' => $plan->features,
                    'is_active' => $plan->is_active,
                    'created_at' => $plan->created_at->toISOString(),
                ], 'Plan created successfully'),
                201
            );
        } catch (\Exception $e) {
            return response()->json(
                ErrorResponse::make(
                    'Failed to create plan',
                    null,
                    'PLAN_CREATION_FAILED',
                    500
                ),
                500
            );
        }
    }

    /**
     * Get plan details
     */
    public function show($id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(
                ErrorResponse::notFound('Plan'),
                404
            );
        }

        $planDetails = $this->planService->getPlanDetails($plan);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $planDetails->id,
                'name' => $planDetails->name,
                'description' => $planDetails->description,
                'features' => $planDetails->features,
                'is_active' => $planDetails->is_active,
                'billing_cycles' => $planDetails->billingCycles->map(function ($cycle) {
                    return [
                        'id' => $cycle->id,
                        'cycle_type' => $cycle->cycle_type,
                        'display_name' => $cycle->display_name,
                        'duration_in_days' => $cycle->duration_in_days,
                        'prices' => $cycle->prices->where('is_active', true)->map(function ($price) {
                            return [
                                'id' => $price->id,
                                'currency' => $price->currency,
                                'price' => $price->price,
                                'formatted_price' => $this->formatPrice($price),
                            ];
                        }),
                    ];
                }),
                'available_currencies' => $this->planService->getAvailableCurrencies($plan)->values(),
                'created_at' => $planDetails->created_at->toISOString(),
                'updated_at' => $planDetails->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update plan
     */
    public function update(UpdatePlanRequest $request, $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(
                ErrorResponse::notFound('Plan'),
                404
            );
        }

        try {
            $updatedPlan = $this->planService->updatePlan($plan, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
                'data' => [
                    'id' => $updatedPlan->id,
                    'name' => $updatedPlan->name,
                    'description' => $updatedPlan->description,
                    'features' => $updatedPlan->features,
                    'is_active' => $updatedPlan->is_active,
                    'updated_at' => $updatedPlan->updated_at->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete plan (soft delete or deactivate)
     */
    public function destroy($id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(
                ErrorResponse::notFound('Plan'),
                404
            );
        }

        try {
            $this->planService->deactivatePlan($plan);

            return response()->json([
                'success' => true,
                'message' => 'Plan deactivated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add billing cycle to plan
     */
    public function addBillingCycle(Request $request, $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(
                ErrorResponse::notFound('Plan'),
                404
            );
        }

        $validated = $request->validate([
            'cycle_type' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'semi_annual', 'yearly'])],
            'duration_in_days' => 'required|integer|min:1|max:365',
            'display_name' => 'nullable|string|max:50',
        ]);

        try {
            $cycle = $this->planService->addBillingCycle(
                $plan,
                $validated['cycle_type'],
                $validated['duration_in_days'],
                $validated['display_name'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Billing cycle added successfully',
                'data' => [
                    'id' => $cycle->id,
                    'plan_id' => $cycle->plan_id,
                    'cycle_type' => $cycle->cycle_type,
                    'display_name' => $cycle->display_name,
                    'duration_in_days' => $cycle->duration_in_days,
                    'created_at' => $cycle->created_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add billing cycle',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add pricing to billing cycle
     */
    public function addPricing(Request $request, $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(
                ErrorResponse::notFound('Plan'),
                404
            );
        }

        $validated = $request->validate([
            'plan_billing_cycle_id' => 'required|exists:plan_billing_cycles,id',
            'currency' => 'required|string|size:3|uppercase',
            'price' => 'required|numeric|min:0|max:999999.99',
        ]);

        try {
            $cycle = $plan->billingCycles()->findOrFail($validated['plan_billing_cycle_id']);

            $price = $this->planService->addPricing(
                $cycle,
                $validated['currency'],
                $validated['price']
            );

            return response()->json([
                'success' => true,
                'message' => 'Pricing added successfully',
                'data' => [
                    'id' => $price->id,
                    'plan_billing_cycle_id' => $price->plan_billing_cycle_id,
                    'currency' => $price->currency,
                    'price' => $price->price,
                    'formatted_price' => $this->formatPrice($price),
                    'is_active' => $price->is_active,
                    'created_at' => $price->created_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add pricing',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get plan pricing by currency and cycle
     */
    public function getPricing(Request $request, $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(
                ErrorResponse::notFound('Plan'),
                404
            );
        }

        $validated = $request->validate([
            'currency' => 'required|string|size:3|uppercase',
            'cycle' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'semi_annual', 'yearly'])],
        ]);

        $price = $this->planService->getPricingByCurrencyAndCycle(
            $plan,
            $validated['currency'],
            $validated['cycle']
        );

        if (!$price) {
            return response()->json([
                'success' => false,
                'message' => 'Pricing not found for the specified currency and billing cycle',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $price->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'billing_cycle' => $validated['cycle'],
                'currency' => $price->currency,
                'price' => $price->price,
                'formatted_price' => $this->formatPrice($price),
                'is_active' => $price->is_active,
            ],
        ]);
    }

    /**
     * Format price for display
     */
    private function formatPrice($price): string
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