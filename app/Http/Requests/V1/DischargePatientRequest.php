<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class DischargePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discharge_type_id' => 'required|integer|exists:terms,id',
            'visit_outcome_id' => 'nullable|integer|exists:terms,id',
            'discharged_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'discharge_type_id.required' => 'Discharge type is required',
            'discharge_type_id.exists' => 'Invalid discharge type',
            'visit_outcome_id.exists' => 'Invalid visit outcome',
            'discharged_at.date' => 'Discharge date must be a valid date',
        ];
    }
}