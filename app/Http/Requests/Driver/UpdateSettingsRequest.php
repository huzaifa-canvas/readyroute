<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every toggle is optional so the app can send just the one that changed.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_notifications' => ['sometimes', 'boolean'],
            'dark_mode'          => ['sometimes', 'boolean'],
            'offline_navigation' => ['sometimes', 'boolean'],
        ];
    }
}
