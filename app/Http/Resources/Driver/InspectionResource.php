<?php

namespace App\Http\Resources\Driver;

use App\Models\InspectionResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Today's pre-trip inspection, as the DVIR screen renders it.
 */
class InspectionResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly array $progress = [],
        private readonly ?string $signatureUrl = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'date' => optional($this->inspection_date)->toDateString(),

            'status'       => $this->status?->value,
            'is_submitted' => $this->isSubmitted(),
            'has_defects'  => (bool) $this->has_defects,
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),

            'vehicle' => $this->whenLoaded(
                'vehicle',
                fn () => $this->vehicle ? new VehicleResource($this->vehicle) : null
            ),

            'progress' => $this->progress,

            'items' => $this->whenLoaded('responses', fn () => $this->responses
                ->sortBy(fn (InspectionResponse $r) => $r->item?->sort_order ?? 0)
                ->values()
                ->map(fn (InspectionResponse $r) => [
                    'item_id'     => $r->inspection_item_id,
                    'label'       => $r->item?->label,
                    'is_required' => (bool) $r->item?->is_required,
                    'status'      => $r->status?->value,
                    'status_label'=> $r->status?->label(),
                    'note'        => $r->note,
                    'checked_at'  => optional($r->checked_at)->toIso8601String(),
                ])),

            'signature' => [
                'required'  => true,
                'collected' => $this->signature_path !== null,
                'image_url' => $this->signatureUrl,
            ],
        ];
    }
}
