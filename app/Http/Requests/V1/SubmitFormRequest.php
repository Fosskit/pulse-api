<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_data' => 'required|array',
            'form_data.*' => 'nullable', // Allow any form field values
            'completed_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'form_data.required' => 'Form data is required',
            'form_data.array' => 'Form data must be an array',
            'completed_at.date' => 'Completed date must be a valid date',
        ];
    }
}