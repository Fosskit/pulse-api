<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;

class DischargeVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discharged_at' => ['required', 'date', 'after:' . $this->route('visit')->admitted_at],
            'discharge_type_id' => ['required', 'exists:taxonomy_values,id'],
            'visit_outcome_id' => ['required', 'exists:taxonomy_values,id'],
            'discharge_summary' => ['nullable', 'string', 'max:2000'],
            'discharge_instructions' => ['nullable', 'string', 'max:2000'],
            'discharge_medications' => ['nullable', 'array'],
            'discharge_medications.*.medication_name' => ['required', 'string', 'max:200'],
            'discharge_medications.*.dosage' => ['required', 'string', 'max:100'],
            'discharge_medications.*.frequency' => ['required', 'string', 'max:100'],
            'discharge_medications.*.duration' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'discharged_at.required' => 'Discharge date and time is required',
            'discharged_at.after' => 'Discharge time must be after admission time',
            'discharge_type_id.required' => 'Discharge type is required',
            'visit_outcome_id.required' => 'Visit outcome is required',
        ];
    }
}