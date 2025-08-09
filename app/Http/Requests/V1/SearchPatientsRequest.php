<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SearchPatientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'sometimes|string|max:255',
            'identity_code' => 'sometimes|string|max:255',
            'facility_id' => 'sometimes|integer|exists:facilities,id',
            'has_active_insurance' => 'sometimes|boolean',
            'sex' => 'sometimes|in:Male,Female',
            'age_min' => 'sometimes|integer|min:0|max:150',
            'age_max' => 'sometimes|integer|min:0|max:150|gte:age_min',
            'is_deceased' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
            
            // Demographics search
            'demographics' => 'sometimes|array',
            'demographics.surname' => 'sometimes|string|max:255',
            'demographics.name' => 'sometimes|string|max:255',
            'demographics.phone' => 'sometimes|string|max:20',
            'demographics.sex' => 'sometimes|in:Male,Female',
            'demographics.birthdate' => 'sometimes|date',
            
            // Address search
            'address' => 'sometimes|array',
            'address.province_id' => 'sometimes|integer|exists:gazetteers,id',
            'address.district_id' => 'sometimes|integer|exists:gazetteers,id',
            'address.commune_id' => 'sometimes|integer|exists:gazetteers,id',
            'address.village_id' => 'sometimes|integer|exists:gazetteers,id',
            'address.street_address' => 'sometimes|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'sex.in' => 'Sex must be either Male or Female',
            'age_min.min' => 'Minimum age must be at least 0',
            'age_min.max' => 'Minimum age cannot exceed 150',
            'age_max.min' => 'Maximum age must be at least 0',
            'age_max.max' => 'Maximum age cannot exceed 150',
            'age_max.gte' => 'Maximum age must be greater than or equal to minimum age',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 100',
            'facility_id.exists' => 'Selected facility does not exist',
            'address.province_id.exists' => 'Selected province does not exist',
            'address.district_id.exists' => 'Selected district does not exist',
            'address.commune_id.exists' => 'Selected commune does not exist',
            'address.village_id.exists' => 'Selected village does not exist',
        ];
    }
}