<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckRoomAvailabilityRequest extends FormRequest
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
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'room_ids' => 'sometimes|array',
            'room_ids.*' => 'integer|exists:rooms,id',
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
            'start_time.date' => 'The start time must be a valid date.',
            'end_time.date' => 'The end time must be a valid date.',
            'end_time.after' => 'The end time must be after the start time.',
            'room_ids.array' => 'The room IDs must be an array.',
            'room_ids.*.integer' => 'Each room ID must be an integer.',
            'room_ids.*.exists' => 'One or more room IDs do not exist.',
        ];
    }
}