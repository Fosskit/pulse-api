<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransferPatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization will be handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visit_id' => 'required|integer|exists:visits,id',
            'destination_room_id' => 'required|integer|exists:rooms,id',
            'reason' => 'sometimes|string|max:500',
            'notes' => 'sometimes|string|max:1000',
            'transfer_time' => 'sometimes|date|after_or_equal:now',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required.',
            'visit_id.integer' => 'Visit ID must be an integer.',
            'visit_id.exists' => 'The specified visit does not exist.',
            'destination_room_id.required' => 'Destination room ID is required.',
            'destination_room_id.integer' => 'Destination room ID must be an integer.',
            'destination_room_id.exists' => 'The specified destination room does not exist.',
            'reason.string' => 'Transfer reason must be a string.',
            'reason.max' => 'Transfer reason may not be greater than 500 characters.',
            'notes.string' => 'Transfer notes must be a string.',
            'notes.max' => 'Transfer notes may not be greater than 1000 characters.',
            'transfer_time.date' => 'Transfer time must be a valid date.',
            'transfer_time.after_or_equal' => 'Transfer time cannot be in the past.',
        ];
    }
}