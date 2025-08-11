<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'visit_id' => 'required|exists:visits,id',
            'status_id' => 'required|exists:terms,id',
            'intent_id' => 'required|exists:terms,id',
            'medication_id' => 'required|exists:terms,id',
            'requester_id' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'unit_id' => 'required|exists:terms,id',
            'instruction' => 'required|array',
            'instruction.method_id' => 'required|exists:terms,id',
            'instruction.unit_id' => 'nullable|exists:terms,id',
            'instruction.morning' => 'nullable|numeric|min:0|max:999.99',
            'instruction.afternoon' => 'nullable|numeric|min:0|max:999.99',
            'instruction.evening' => 'nullable|numeric|min:0|max:999.99',
            'instruction.night' => 'nullable|numeric|min:0|max:999.99',
            'instruction.days' => 'required|integer|min:1|max:365',
            'instruction.quantity' => 'nullable|numeric|min:0|max:99999.99',
            'instruction.note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required.',
            'visit_id.exists' => 'The specified visit does not exist.',
            'status_id.required' => 'Status is required.',
            'status_id.exists' => 'The specified status does not exist.',
            'intent_id.required' => 'Intent is required.',
            'intent_id.exists' => 'The specified intent does not exist.',
            'medication_id.required' => 'Medication is required.',
            'medication_id.exists' => 'The specified medication does not exist.',
            'requester_id.required' => 'Requester is required.',
            'requester_id.exists' => 'The specified requester does not exist.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'unit_id.required' => 'Unit is required.',
            'unit_id.exists' => 'The specified unit does not exist.',
            'instruction.required' => 'Medication instruction is required.',
            'instruction.method_id.required' => 'Administration method is required.',
            'instruction.method_id.exists' => 'The specified administration method does not exist.',
            'instruction.unit_id.exists' => 'The specified instruction unit does not exist.',
            'instruction.morning.numeric' => 'Morning dose must be a number.',
            'instruction.morning.min' => 'Morning dose cannot be negative.',
            'instruction.morning.max' => 'Morning dose cannot exceed 999.99.',
            'instruction.afternoon.numeric' => 'Afternoon dose must be a number.',
            'instruction.afternoon.min' => 'Afternoon dose cannot be negative.',
            'instruction.afternoon.max' => 'Afternoon dose cannot exceed 999.99.',
            'instruction.evening.numeric' => 'Evening dose must be a number.',
            'instruction.evening.min' => 'Evening dose cannot be negative.',
            'instruction.evening.max' => 'Evening dose cannot exceed 999.99.',
            'instruction.night.numeric' => 'Night dose must be a number.',
            'instruction.night.min' => 'Night dose cannot be negative.',
            'instruction.night.max' => 'Night dose cannot exceed 999.99.',
            'instruction.days.required' => 'Number of days is required.',
            'instruction.days.integer' => 'Number of days must be a whole number.',
            'instruction.days.min' => 'Number of days must be at least 1.',
            'instruction.days.max' => 'Number of days cannot exceed 365.',
            'instruction.quantity.numeric' => 'Instruction quantity must be a number.',
            'instruction.quantity.min' => 'Instruction quantity cannot be negative.',
            'instruction.quantity.max' => 'Instruction quantity cannot exceed 99999.99.',
            'instruction.note.string' => 'Instruction note must be text.',
            'instruction.note.max' => 'Instruction note cannot exceed 1000 characters.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $instruction = $this->input('instruction', []);
            
            // Validate that at least one dose is provided
            $totalDose = ($instruction['morning'] ?? 0) + 
                        ($instruction['afternoon'] ?? 0) + 
                        ($instruction['evening'] ?? 0) + 
                        ($instruction['night'] ?? 0);

            if ($totalDose <= 0) {
                $validator->errors()->add(
                    'instruction.doses', 
                    'At least one dose (morning, afternoon, evening, or night) must be greater than 0.'
                );
            }
        });
    }
}