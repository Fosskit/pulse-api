<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'completed_at' => ['nullable', 'date'],
            'performed_at' => ['nullable', 'date'],
            'performed_by' => ['nullable', 'integer'],
            'outcome_id' => ['nullable', 'integer', 'exists:concepts,id'],
            
            // Results array for creating observations
            'results' => ['nullable', 'array'],
            'results.*.concept_id' => ['required_with:results', 'integer', 'exists:concepts,id'],
            'results.*.observation_status_id' => ['nullable', 'integer', 'exists:terms,id'],
            'results.*.code' => ['nullable', 'string', 'max:77'],
            'results.*.body_site_id' => ['nullable', 'integer', 'exists:concepts,id'],
            'results.*.value_id' => ['nullable', 'integer'],
            'results.*.value_string' => ['nullable', 'string', 'max:190'],
            'results.*.value_number' => ['nullable', 'numeric'],
            'results.*.value_datetime' => ['nullable', 'date'],
            'results.*.value_boolean' => ['nullable', 'boolean'],
            'results.*.reference_range_low' => ['nullable', 'numeric'],
            'results.*.reference_range_high' => ['nullable', 'numeric'],
            'results.*.reference_range_text' => ['nullable', 'string'],
            'results.*.interpretation' => ['nullable', 'string'],
            'results.*.comments' => ['nullable', 'string'],
            'results.*.verified_at' => ['nullable', 'date'],
            'results.*.verified_by' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'completed_at.date' => 'Completed at must be a valid date',
            'performed_at.date' => 'Performed at must be a valid date',
            'performed_by.integer' => 'Performed by must be a valid user ID',
            'outcome_id.exists' => 'The specified outcome does not exist',
            'results.array' => 'Results must be an array',
            'results.*.concept_id.required_with' => 'Concept ID is required for each result',
            'results.*.concept_id.exists' => 'The specified concept does not exist',
            'results.*.observation_status_id.exists' => 'The specified observation status does not exist',
            'results.*.body_site_id.exists' => 'The specified body site does not exist',
            'results.*.value_number.numeric' => 'Value number must be numeric',
            'results.*.value_datetime.date' => 'Value datetime must be a valid date',
            'results.*.value_boolean.boolean' => 'Value boolean must be true or false',
            'results.*.reference_range_low.numeric' => 'Reference range low must be numeric',
            'results.*.reference_range_high.numeric' => 'Reference range high must be numeric',
            'results.*.verified_at.date' => 'Verified at must be a valid date',
            'results.*.verified_by.integer' => 'Verified by must be a valid user ID',
        ];
    }
}