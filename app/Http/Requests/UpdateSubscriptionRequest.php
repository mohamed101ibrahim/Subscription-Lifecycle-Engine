<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');
        return auth()->check() && auth()->user()->id === $subscription->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $subscriptionId = $this->route('subscription')?->id;

        return [
            'plan_billing_cycle_id' => [
                'nullable',
                'exists:plan_billing_cycles,id',
                Rule::exists('plan_billing_cycles', 'id')->where(function ($query) {
                    // Use the current subscription's plan_id if not changing plan
                    $planId = $this->route('subscription')->plan_id;
                    $query->where('plan_id', $planId);
                }),
            ],
            'currency' => 'nullable|string|size:3|uppercase',
            'auto_renew' => 'nullable|boolean',
            'reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_billing_cycle_id.exists' => 'The selected billing cycle is invalid for this subscription.',
            'currency.size' => 'Currency must be exactly 3 characters (e.g., USD, EUR, AED).',
            'auto_renew.boolean' => 'Auto renew must be a boolean value.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'plan_billing_cycle_id' => 'billing cycle',
            'currency' => 'currency',
            'auto_renew' => 'auto renew setting',
            'reason' => 'reason',
        ];
    }
}
