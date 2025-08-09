<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'sex' => ['required', 'in:M,F'],
            'birthdate' => ['required', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'nationality_id' => ['required', 'exists:taxonomy_values,id'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'marital_status_id' => ['nullable', 'exists:taxonomy_values,id'],
            'occupation_id' => ['nullable', 'exists:taxonomy_values,id'],
            'death_at' => ['nullable', 'date', 'after:birthdate'],
            
            // Address
            'address.address_type_id' => ['nullable', 'exists:taxonomy_values,id'],
            'address.province_id' => ['nullable', 'exists:gazetteers,id'],
            'address.district_id' => ['nullable', 'exists:gazetteers,id'],
            'address.commune_id' => ['nullable', 'exists:gazetteers,id'],
            'address.village_id' => ['nullable', 'exists:gazetteers,id'],
            'address.street_address' => ['nullable', 'string', 'max:500'],
            
            // Identities
            'identities.*.card_type_id' => ['required_with:identities', 'exists:taxonomy_values,id'],
            'identities.*.code' => ['required_with:identities', 'string', 'max:100'],
            'identities.*.issued_date' => ['required_with:identities', 'date'],
            'identities.*.expired_date' => ['nullable', 'date', 'after:identities.*.issued_date'],
            
            // Disabilities
            'disabilities' => ['nullable', 'array'],
            'disabilities.*' => ['exists:taxonomy_values,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'surname.required' => 'Patient surname is required',
            'name.required' => 'Patient name is required',
            'sex.required' => 'Patient sex is required',
            'sex.in' => 'Sex must be either Male (M) or Female (F)',
            'birthdate.required' => 'Date of birth is required',
            'birthdate.before' => 'Date of birth must be before today',
            'nationality_id.required' => 'Nationality is required',
            'nationality_id.exists' => 'Selected nationality is invalid',
            'facility_id.required' => 'Health facility is required',
            'facility_id.exists' => 'Selected facility is invalid',
        ];
    }
}