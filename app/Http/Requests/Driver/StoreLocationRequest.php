<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts either one fix or a batch of them.
 *
 * A phone that posts every fix as its own request drains the battery and
 * multiplies server load for no benefit, so the app is free to buffer a minute
 * of movement and send it in one call. This is a battery and bandwidth
 * measure, not offline support: the app is still expected to be online.
 */
class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Single fix
            'lat' => ['required_without:points', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_without:points', 'nullable', 'numeric', 'between:-180,180'],
            'speed_mph'   => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:200'],
            'heading'     => ['sometimes', 'nullable', 'numeric', 'between:0,360'],
            'accuracy_m'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'recorded_at' => ['sometimes', 'nullable', 'date'],
            'trip_id'     => ['sometimes', 'nullable', 'integer'],

            // Batch
            'points'               => ['required_without:lat', 'nullable', 'array', 'max:200'],
            'points.*.lat'         => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng'         => ['required', 'numeric', 'between:-180,180'],
            'points.*.speed_mph'   => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:200'],
            'points.*.heading'     => ['sometimes', 'nullable', 'numeric', 'between:0,360'],
            'points.*.accuracy_m'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'points.*.recorded_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Normalise both shapes into one list of fixes, oldest first.
     */
    public function points(): array
    {
        $points = $this->input('points');

        if (! is_array($points) || $points === []) {
            $points = [[
                'lat'         => $this->input('lat'),
                'lng'         => $this->input('lng'),
                'speed_mph'   => $this->input('speed_mph'),
                'heading'     => $this->input('heading'),
                'accuracy_m'  => $this->input('accuracy_m'),
                'recorded_at' => $this->input('recorded_at'),
            ]];
        }

        usort($points, function ($a, $b) {
            return strtotime($a['recorded_at'] ?? 'now') <=> strtotime($b['recorded_at'] ?? 'now');
        });

        return $points;
    }
}
