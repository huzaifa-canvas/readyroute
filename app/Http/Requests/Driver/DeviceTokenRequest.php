<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class DeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Null clears the token, which is what the app sends when the user
            // turns push off at the OS level.
            'device_token' => ['present', 'nullable', 'string', 'max:255'],
            'platform'     => ['sometimes', 'nullable', 'string', 'in:ios,android'],
        ];
    }
}
