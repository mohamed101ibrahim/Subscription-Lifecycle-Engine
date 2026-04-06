<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanBillingCycleRequest extends FormRequest
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
            'cycle_type' => 'required|in:daily,weekly,monthly,quarterly,semi_annual,yearly',
            'duration_in_days' => 'required|integer|min:1|max:3650',
            'display_name' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cycle_type.required' => 'Cycle type is required.',
            'cycle_type.in' => 'Cycle type must be one of: daily, weekly, monthly, quarterly, semi_annual, yearly.',
            'duration_in_days.required' => 'Duration in days is required.',
            'duration_in_days.integer' => 'Duration must be a whole number.',
            'duration_in_days.min' => 'Duration must be at least 1 day.',
            'duration_in_days.max' => 'Duration cannot exceed 3650 days (10 years).',
            'display_name.max' => 'Display name cannot exceed 50 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'cycle_type' => 'cycle type',
            'duration_in_days' => 'duration',
            'display_name' => 'display name',
        ];
    }
}
