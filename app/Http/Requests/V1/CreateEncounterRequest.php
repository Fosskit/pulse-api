<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => 'required|integer|exists:visits,id',
            'encounter_type_id' => 'required|integer|exists:terms,id',
            'encounter_form_id' => 'nullable|integer',
            'is_new' => 'nullable|boolean',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after_or_equal:started_at',
        ];
    }

    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'Visit not found',
            'encounter_type_id.required' => 'Encounter type is required',
            'encounter_type_id.exists' => 'Invalid encounter type',
            'is_new.boolean' => 'Is new must be true or false',
            'started_at.date' => 'Start date must be a valid date',
            'ended_at.date' => 'End date must be a valid date',
            'ended_at.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}