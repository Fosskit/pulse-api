<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class FacilityUtilizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization will be handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => 'sometimes|date|before_or_equal:end_date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'include_historical' => 'sometimes|boolean',
            'department_ids' => 'sometimes|array',
            'department_ids.*' => 'integer|exists:departments,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.before_or_equal' => 'Start date must be before or equal to end date.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'include_historical.boolean' => 'Include historical must be true or false.',
            'department_ids.array' => 'Department IDs must be an array.',
            'department_ids.*.integer' => 'Each department ID must be an integer.',
            'department_ids.*.exists' => 'One or more department IDs do not exist.',
        ];
    }
}