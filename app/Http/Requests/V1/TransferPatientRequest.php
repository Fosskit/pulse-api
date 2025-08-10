<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransferPatientRequest extends FormRequest
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
            'destination_encounter_form_id' => 'nullable|integer|exists:clinical_form_templates,id',
            'encounter_form_id' => 'nullable|integer|exists:clinical_form_templates,id',
            'transfer_at' => 'nullable|date',
            'reason' => 'nullable|string|max:1000',
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
            'destination_encounter_form_id.exists' => 'Invalid destination clinical form template',
            'encounter_form_id.exists' => 'Invalid clinical form template',
            'transfer_at.date' => 'Transfer date must be a valid date',
            'reason.max' => 'Reason cannot exceed 1000 characters',
        ];
    }
}