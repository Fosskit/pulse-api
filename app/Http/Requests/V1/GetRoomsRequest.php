<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class GetRoomsRequest extends FormRequest
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
            'code' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'room_type_id' => 'sometimes|integer|exists:terms,id',
            'available' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
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
            'code.string' => 'The room code must be a string.',
            'code.max' => 'The room code may not be greater than 255 characters.',
            'name.string' => 'The room name must be a string.',
            'name.max' => 'The room name may not be greater than 255 characters.',
            'room_type_id.integer' => 'The room type ID must be an integer.',
            'room_type_id.exists' => 'The selected room type does not exist.',
            'available.boolean' => 'The available filter must be true or false.',
            'search.string' => 'The search term must be a string.',
            'search.max' => 'The search term may not be greater than 255 characters.',
        ];
    }
}