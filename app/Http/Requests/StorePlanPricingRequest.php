<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanPricingRequest extends FormRequest
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
            'plan_billing_cycle_id' => 'required|exists:plan_billing_cycles,id',
            'currency' => 'required|string|size:3|uppercase|unique:plan_prices,currency,NULL,id,plan_billing_cycle_id,' . $this->input('plan_billing_cycle_id'),
            'price' => 'required|numeric|min:0.01|max:999999.99',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_billing_cycle_id.required' => 'Billing cycle is required.',
            'plan_billing_cycle_id.exists' => 'The selected billing cycle does not exist.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be exactly 3 characters (e.g., USD, EUR, AED).',
            'currency.unique' => 'Pricing for this currency already exists for this billing cycle.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price must be at least 0.01.',
            'price.max' => 'Price cannot exceed 999,999.99.',
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
            'price' => 'price',
        ];
    }
}
