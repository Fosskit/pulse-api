<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
//            'role' => ['required', 'string', 'in:doctor,nurse,admin,technician,pharmacist,receptionist'],
//            'facility_id' => ['required', 'exists:facilities,id'],
//            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required',
            'name.min' => 'Name must be at least 2 characters',
            'email.unique' => 'This email address is already registered',
            'username.unique' => 'This username is already taken',
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores, and hyphens',
            'password.confirmed' => 'Password confirmation does not match',
            'role.in' => 'Invalid role selected',
            'facility_id.exists' => 'Selected facility is invalid'
        ];
    }
}
