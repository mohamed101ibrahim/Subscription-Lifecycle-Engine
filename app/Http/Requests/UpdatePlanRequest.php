<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Resources\ErrorResponse;

class UpdatePlanRequest extends FormRequest
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
        $planId = $this->route('id');

        return [
            'name' => 'nullable|string|max:255|unique:plans,name,' . $planId,
            'description' => 'nullable|string|max:1000',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A plan with this name already exists.',
            'name.max' => 'Plan name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'features.array' => 'Features must be an array.',
            'features.*.string' => 'Each feature must be a string.',
            'is_active.boolean' => 'Active status must be a boolean value.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'plan name',
            'description' => 'plan description',
            'features' => 'plan features',
            'is_active' => 'active status',
        ];
    }
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(
                ErrorResponse::make(
                    'The given data was invalid.',
                    $validator->errors()->toArray(),
                    'VALIDATION_ERROR',
                    422
                ),
                422
            )
        );
    }
}
