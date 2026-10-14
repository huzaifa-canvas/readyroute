<?php

namespace App\Http\Resources\Dispatcher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A client. The list card prints name, phone and funding source; the profile
 * screen adds the address, requirements and emergency contact.
 */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'full_name' => $this->full_name,
            'phone'     => $this->phone_number,
            'email'     => $this->email,
            'dob'       => optional($this->dob)->toDateString(),

            'funding_type'  => $this->funding_type,
            // The screens print this beside the phone number, capitalised.
            'funding_label' => $this->funding_type ? ucfirst($this->funding_type) : null,
            'insurance_id'  => $this->insurance_id,

            'address' => [
                'line'     => $this->home_address,
                'apt_unit' => $this->apt_unit,
                'city'     => $this->city,
                'zip_code' => $this->zip_code,
                // One line as the profile header shows it.
                'full'     => collect([$this->home_address, $this->city, $this->zip_code])
                    ->filter()->implode(', ') ?: null,
            ],

            'emergency_contact' => [
                'name'  => $this->emergency_contact_name,
                'phone' => $this->emergency_contact_phone,
            ],

            // What the client needs on every ride, so the office can match a
            // vehicle without opening the notes.
            'requirements' => [
                'wheelchair_required'   => (bool) $this->wheelchair_required,
                'ambulatory_assistance' => (bool) $this->ambulatory_assistance,
                'stretcher_transport'   => (bool) $this->stretcher_transport,
                'bariatric_vehicle'     => (bool) $this->bariatric_vehicle,
            ],
            'requirement_labels' => array_keys(array_filter([
                'Wheelchair'            => (bool) $this->wheelchair_required,
                'Ambulatory assistance' => (bool) $this->ambulatory_assistance,
                'Stretcher'             => (bool) $this->stretcher_transport,
                'Bariatric vehicle'     => (bool) $this->bariatric_vehicle,
            ])),

            'special_notes' => $this->special_notes,

            'notes' => ClientNoteResource::collection($this->whenLoaded('notes')),

            'trip_count' => $this->whenCounted('trips'),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
