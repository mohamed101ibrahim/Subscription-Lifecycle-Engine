<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSubscriptionRequest extends FormRequest
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
            'plan_id' => 'required|exists:plans,id',
            'plan_billing_cycle_id' => [
                'required',
                'exists:plan_billing_cycles,id',
                Rule::exists('plan_billing_cycles', 'id')->where(function ($query) {
                    $query->where('plan_id', $this->input('plan_id'));
                }),
            ],
            'currency' => 'required|string|size:3|uppercase',
            'trial_period_days' => 'nullable|integer|min:0|max:365',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_billing_cycle_id.exists' => 'The selected billing cycle does not belong to the specified plan.',
            'currency.size' => 'Currency must be exactly 3 characters (e.g., USD, EUR).',
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
            'currency' => 'currency code',
            'trial_period_days' => 'trial period',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if user already has an active subscription to this plan
            if ($this->user() && $this->input('plan_id')) {
                $existingSubscription = $this->user()
                    ->subscriptions()
                    ->where('plan_id', $this->input('plan_id'))
                    ->whereIn('status', ['trialing', 'active'])
                    ->exists();

                if ($existingSubscription) {
                    $validator->errors()->add('plan_id', 'You already have an active subscription to this plan.');
                }
            }

            // Validate that pricing exists for the selected plan/cycle/currency
            if ($this->input('plan_id') && $this->input('plan_billing_cycle_id') && $this->input('currency')) {
                $pricingExists = \DB::table('plan_prices')
                    ->join('plan_billing_cycles', 'plan_prices.plan_billing_cycle_id', '=', 'plan_billing_cycles.id')
                    ->where('plan_billing_cycles.plan_id', $this->input('plan_id'))
                    ->where('plan_billing_cycles.id', $this->input('plan_billing_cycle_id'))
                    ->where('plan_prices.currency', strtoupper($this->input('currency')))
                    ->where('plan_prices.is_active', true)
                    ->exists();

                if (!$pricingExists) {
                    $validator->errors()->add('currency', 'No pricing available for the selected plan, billing cycle, and currency combination.');
                }
            }
        });
    }
}