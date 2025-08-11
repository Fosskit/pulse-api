<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class RecordMedicationAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'medication_request_id' => 'required|exists:medication_requests,id',
            'status_id' => 'required|exists:terms,id',
            'administrator_id' => 'required|exists:users,id',
            'dose_given' => 'required|numeric|min:0.01|max:999.99',
            'dose_unit_id' => 'required|exists:terms,id',
            'administered_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'vital_signs_before' => 'nullable|array',
            'vital_signs_before.temperature' => 'nullable|numeric|min:30|max:50',
            'vital_signs_before.blood_pressure_systolic' => 'nullable|integer|min:50|max:300',
            'vital_signs_before.blood_pressure_diastolic' => 'nullable|integer|min:30|max:200',
            'vital_signs_before.heart_rate' => 'nullable|integer|min:30|max:250',
            'vital_signs_before.respiratory_rate' => 'nullable|integer|min:5|max:60',
            'vital_signs_before.oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'vital_signs_after' => 'nullable|array',
            'vital_signs_after.temperature' => 'nullable|numeric|min:30|max:50',
            'vital_signs_after.blood_pressure_systolic' => 'nullable|integer|min:50|max:300',
            'vital_signs_after.blood_pressure_diastolic' => 'nullable|integer|min:30|max:200',
            'vital_signs_after.heart_rate' => 'nullable|integer|min:30|max:250',
            'vital_signs_after.respiratory_rate' => 'nullable|integer|min:5|max:60',
            'vital_signs_after.oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'adverse_reactions' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'medication_request_id.required' => 'Medication request ID is required.',
            'medication_request_id.exists' => 'The specified medication request does not exist.',
            'status_id.required' => 'Status is required.',
            'status_id.exists' => 'The specified status does not exist.',
            'administrator_id.required' => 'Administrator is required.',
            'administrator_id.exists' => 'The specified administrator does not exist.',
            'dose_given.required' => 'Dose given is required.',
            'dose_given.numeric' => 'Dose given must be a number.',
            'dose_given.min' => 'Dose given must be at least 0.01.',
            'dose_given.max' => 'Dose given cannot exceed 999.99.',
            'dose_unit_id.required' => 'Dose unit is required.',
            'dose_unit_id.exists' => 'The specified dose unit does not exist.',
            'administered_at.date' => 'Administration date must be a valid date.',
            'notes.string' => 'Notes must be text.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'adverse_reactions.string' => 'Adverse reactions must be text.',
            'adverse_reactions.max' => 'Adverse reactions cannot exceed 2000 characters.',
        ];
    }
}