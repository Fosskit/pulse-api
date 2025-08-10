<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class GetFacilitiesRequest extends FormRequest
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
            'code.string' => 'The facility code must be a string.',
            'code.max' => 'The facility code may not be greater than 255 characters.',
            'search.string' => 'The search term must be a string.',
            'search.max' => 'The search term may not be greater than 255 characters.',
        ];
    }
}