<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string']
        ];
    }

    public function messages(): array
    {
        return [
            'refresh_token.required' => 'Refresh token is required',
            'refresh_token.string' => 'Refresh token must be a valid string'
        ];
    }
}
