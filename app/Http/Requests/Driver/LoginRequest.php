<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],

            // The login screen has a role selector. It is accepted so the app
            // can send what the user picked, but access is decided by the
            // account's own role, never by what the client claims.
            'role'     => ['sometimes', 'nullable', 'string', 'in:driver'],

            // Registered at login so push works from the first session.
            'device_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_name'  => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
