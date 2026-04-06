<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id|exists:plans,id,is_active,1',
            'plan_billing_cycle_id' => [
                'required',
                'exists:plan_billing_cycles,id',
                Rule::exists('plan_billing_cycles', 'id')->where(function ($query) {
                    $query->where('plan_id', $this->input('plan_id'));
                }),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                'uppercase',
                Rule::exists('plan_prices', 'currency')->where(function ($query) {
                    $query->where('plan_billing_cycle_id', $this->input('plan_billing_cycle_id'))
                          ->where('is_active', 1);
                }),
            ],
            'trial_period_days' => 'nullable|integer|min:0|max:365',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Plan is required.',
            'plan_id.exists' => 'The selected plan does not exist.',
            'plan_billing_cycle_id.required' => 'Billing cycle is required.',
            'plan_billing_cycle_id.exists' => 'The selected billing cycle does not belong to the specified plan.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be exactly 3 characters (e.g., USD, EUR, AED).',
            'currency.exists' => 'The selected currency is not available for this billing cycle.',
            'trial_period_days.integer' => 'Trial period must be a number.',
            'trial_period_days.max' => 'Trial period cannot exceed 365 days.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'plan_id' => 'plan',
            'plan_billing_cycle_id' => 'billing cycle',
            'currency' => 'currency',
            'trial_period_days' => 'trial period',
        ];
    }
}
