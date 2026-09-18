<?php

namespace App\Http\Requests\Driver;

use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settable = array_map(
            fn (TripStatus $status) => $status->value,
            TripStatus::driverSettable()
        );

        return [
            'status' => ['required', 'string', Rule::in($settable)],

            // Where the driver was when they reported the event. Optional so a
            // status can still be recorded when the device has no fix.
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'That is not a status a driver can set.',
        ];
    }

    public function status(): TripStatus
    {
        return TripStatus::from($this->input('status'));
    }
}
