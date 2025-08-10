<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransferPatientEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => 'required|integer|exists:visits,id',
            'destination_department_id' => 'nullable|integer|exists:departments,id',
            'destination_room_id' => 'nullable|integer|exists:rooms,id',
            'destination_encounter_type_id' => 'nullable|integer|exists:terms,id',
            'destination_encounter_form_id' => 'nullable|integer',
            'encounter_form_id' => 'nullable|integer',
            'transfer_at' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'Visit not found',
            'destination_department_id.exists' => 'Destination department not found',
            'destination_room_id.exists' => 'Destination room not found',
            'destination_encounter_type_id.exists' => 'Invalid destination encounter type',
            'transfer_at.date' => 'Transfer date must be a valid date',
            'reason.max' => 'Reason cannot exceed 500 characters',
        ];
    }
}