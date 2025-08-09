<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: Implement proper authorization
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255|unique:patients,code',
            'facility_id' => 'required|integer|exists:facilities,id',
            
            // Demographics
            'demographics' => 'sometimes|array',
            'demographics.name' => 'sometimes|array',
            'demographics.name.given' => 'sometimes|array',
            'demographics.name.family' => 'sometimes|string|max:255',
            'demographics.birthdate' => 'sometimes|date',
            'demographics.sex' => 'sometimes|in:Male,Female',
            'demographics.telecom' => 'sometimes|array',
            'demographics.address' => 'sometimes|array',
            'demographics.nationality_id' => 'sometimes|integer|exists:terms,id',
            'demographics.telephone' => 'sometimes|string|max:20',
            
            // Addresses
            'addresses' => 'sometimes|array',
            'addresses.*.province_id' => 'required_with:addresses|integer|exists:gazetteers,id',
            'addresses.*.district_id' => 'required_with:addresses|integer|exists:gazetteers,id',
            'addresses.*.commune_id' => 'required_with:addresses|integer|exists:gazetteers,id',
            'addresses.*.village_id' => 'required_with:addresses|integer|exists:gazetteers,id',
            'addresses.*.street_address' => 'sometimes|string|max:500',
            'addresses.*.is_current' => 'sometimes|boolean',
            'addresses.*.address_type_id' => 'sometimes|integer|exists:terms,id',
            
            // Identities
            'identities' => 'sometimes|array',
            'identities.*.code' => 'required_with:identities|string|max:255',
            'identities.*.card_id' => 'required_with:identities|integer|exists:cards,id',
            'identities.*.start_date' => 'required_with:identities|date',
            'identities.*.end_date' => 'sometimes|nullable|date|after:identities.*.start_date',
            'identities.*.detail' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Patient code is required',
            'code.unique' => 'Patient code must be unique',
            'facility_id.required' => 'Facility is required',
            'facility_id.exists' => 'Selected facility does not exist',
            'demographics.name.given.array' => 'Given names must be an array',
            'demographics.sex.in' => 'Sex must be either Male or Female',
            'addresses.*.province_id.exists' => 'Selected province does not exist',
            'addresses.*.district_id.exists' => 'Selected district does not exist',
            'addresses.*.commune_id.exists' => 'Selected commune does not exist',
            'addresses.*.village_id.exists' => 'Selected village does not exist',
            'identities.*.card_id.exists' => 'Selected card does not exist',
            'identities.*.end_date.after' => 'End date must be after start date',
        ];
    }
}