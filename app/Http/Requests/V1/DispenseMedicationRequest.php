<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class DispenseMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'medication_request_id' => 'required|exists:medication_requests,id',
            'status_id' => 'required|exists:terms,id',
            'dispenser_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1|max:9999',
            'unit_id' => 'required|exists:terms,id',
        ];
    }

    public function messages(): array
    {
        return [
            'medication_request_id.required' => 'Medication request ID is required.',
            'medication_request_id.exists' => 'The specified medication request does not exist.',
            'status_id.required' => 'Status is required.',
            'status_id.exists' => 'The specified status does not exist.',
            'dispenser_id.required' => 'Dispenser is required.',
            'dispenser_id.exists' => 'The specified dispenser does not exist.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 9999.',
            'unit_id.required' => 'Unit is required.',
            'unit_id.exists' => 'The specified unit does not exist.',
        ];
    }
}