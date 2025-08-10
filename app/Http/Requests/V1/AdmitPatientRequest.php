<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AdmitPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer|exists:patients,id',
            'facility_id' => 'required|integer|exists:facilities,id',
            'visit_type_id' => 'required|integer|exists:terms,id',
            'admission_type_id' => 'required|integer|exists:terms,id',
            'admitted_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'Patient not found',
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'Facility not found',
            'visit_type_id.required' => 'Visit type is required',
            'visit_type_id.exists' => 'Invalid visit type',
            'admission_type_id.required' => 'Admission type is required',
            'admission_type_id.exists' => 'Invalid admission type',
            'admitted_at.date' => 'Admission date must be a valid date',
        ];
    }
}