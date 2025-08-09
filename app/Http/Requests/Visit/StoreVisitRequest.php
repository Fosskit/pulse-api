<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'health_facility_id' => ['required', 'exists:facilities,id'],
            'visit_type_id' => ['nullable', 'exists:taxonomy_values,id'],
            'admission_type_id' => ['required', 'exists:taxonomy_values,id'],
            'admitted_at' => ['required', 'date'],
            
            // Caretaker information
            'caretaker.name' => ['nullable', 'string', 'max:100'],
            'caretaker.phone' => ['nullable', 'string', 'max:50'],
            'caretaker.sex' => ['nullable', 'in:M,F'],
            'caretaker.relationship_id' => ['required_with:caretaker.name', 'exists:taxonomy_values,id'],
            
            // Visit subject information
            'subject.patient_basic_id' => ['nullable', 'integer'],
            'subject.patient_address_id' => ['nullable', 'integer'],
            'subject.beneficiary_code' => ['nullable', 'string', 'max:100'],
            'subject.card_code' => ['nullable', 'string', 'max:100'],
            'subject.card_type_id' => ['nullable', 'exists:taxonomy_values,id'],
            'subject.start_date' => ['nullable', 'date'],
            'subject.end_date' => ['nullable', 'date', 'after:subject.start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient selection is required',
            'patient_id.exists' => 'Selected patient is invalid',
            'health_facility_id.required' => 'Health facility is required',
            'health_facility_id.exists' => 'Selected facility is invalid',
            'admission_type_id.required' => 'Admission type is required',
            'admitted_at.required' => 'Admission date and time is required',
            'caretaker.relationship_id.required_with' => 'Caretaker relationship is required when caretaker name is provided',
        ];
    }
}