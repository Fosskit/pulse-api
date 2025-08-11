<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'visit_id' => ['required', 'integer', 'exists:visits,id'],
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'request_type' => ['required', Rule::in(['Laboratory', 'Imaging', 'Procedure'])],
            'status_id' => ['required', 'integer', 'exists:terms,id'],
            'ordered_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'integer'],
        ];

        // Add specific validation based on request type
        switch ($this->input('request_type')) {
            case 'Laboratory':
                $rules = array_merge($rules, [
                    'laboratory_data' => ['required', 'array'],
                    'laboratory_data.test_concept_id' => ['required', 'integer', 'exists:concepts,id'],
                    'laboratory_data.specimen_type_concept_id' => ['required', 'integer', 'exists:concepts,id'],
                    'laboratory_data.reason_for_study' => ['nullable', 'string', 'max:1000'],
                ]);
                break;

            case 'Imaging':
                $rules = array_merge($rules, [
                    'imaging_data' => ['required', 'array'],
                    'imaging_data.modality_concept_id' => ['required', 'integer', 'exists:concepts,id'],
                    'imaging_data.body_site_concept_id' => ['required', 'integer', 'exists:concepts,id'],
                    'imaging_data.reason_for_study' => ['nullable', 'string', 'max:1000'],
                ]);
                break;

            case 'Procedure':
                $rules = array_merge($rules, [
                    'procedure_data' => ['required', 'array'],
                    'procedure_data.procedure_concept_id' => ['required', 'integer', 'exists:concepts,id'],
                    'procedure_data.outcome_id' => ['required', 'integer', 'exists:concepts,id'],
                    'procedure_data.body_site_id' => ['required', 'integer', 'exists:concepts,id'],
                ]);
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'The specified visit does not exist',
            'encounter_id.required' => 'Encounter ID is required',
            'encounter_id.exists' => 'The specified encounter does not exist',
            'request_type.required' => 'Request type is required',
            'request_type.in' => 'Request type must be Laboratory, Imaging, or Procedure',
            'status_id.required' => 'Status ID is required',
            'status_id.exists' => 'The specified status does not exist',
            'laboratory_data.required' => 'Laboratory data is required for laboratory requests',
            'imaging_data.required' => 'Imaging data is required for imaging requests',
            'procedure_data.required' => 'Procedure data is required for procedure requests',
        ];
    }
}