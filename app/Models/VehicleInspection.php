<?php

namespace App\Models;

use App\Enums\InspectionItemStatus;
use App\Enums\InspectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'driver_id',
        'vehicle_id',
        'inspection_date',
        'status',
        'has_defects',
        'signature_path',
        'submitted_at',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'status'          => InspectionStatus::class,
        'has_defects'     => 'boolean',
        'submitted_at'    => 'datetime',
    ];

    protected $hidden = [
        'signature_path',
    ];

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function responses()
    {
        return $this->hasMany(InspectionResponse::class);
    }

    /**
     * The whole checklist as it stood for this check, answered or not.
     *
     * A driver only leaves a row behind for an item they touched, so reading
     * the responses alone shows the questions that were answered and silently
     * drops the ones that were skipped. On a check that was signed off without
     * being filled in, that left the screen saying "no items" — true, and
     * useless, because the point of looking is to see which checks were
     * missed.
     *
     * Retired items still appear when this check answered them: the checklist
     * is a record of what was asked that day, not of what is asked now.
     *
     * @param  \Illuminate\Support\Collection<int, InspectionItem>  $items
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function checklist(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $answers = $this->responses->keyBy('inspection_item_id');

        $answered = $this->responses
            ->map->item
            ->filter()
            ->reject(fn (InspectionItem $item) => $items->contains('id', $item->id));

        return $items->concat($answered)->map(function (InspectionItem $item) use ($answers) {
            $response = $answers->get($item->id);
            $status   = $response?->status;

            return [
                'item_id'  => $item->id,
                'label'    => $item->label,
                'required' => (bool) $item->is_required,
                'retired'  => ! $item->is_active,

                // 'pending' is a row the driver opened and left; no row at all
                // is a question never reached. Both read as unanswered here,
                // because neither is an answer.
                'status'       => $status?->value ?? 'not_answered',
                'status_label' => match ($status?->value) {
                    'pass'    => 'Pass',
                    'fail'    => 'Defect',
                    'pending' => 'Not checked',
                    default   => 'Not answered',
                },
                'is_pass'     => (bool) $response?->isPass(),
                'is_defect'   => (bool) $response?->isDefect(),
                'is_answered' => (bool) ($response?->isPass() || $response?->isDefect()),

                'note'       => $response?->note,
                'checked_at' => optional($response?->checked_at)->toIso8601String(),
            ];
        })->values();
    }

    /**
     * How this check reads at a glance: "5 passed, 1 defect, 1 not answered".
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $checklist
     * @return array<string, int>
     */
    public static function checklistTally(\Illuminate\Support\Collection $checklist): array
    {
        return [
            'total'        => $checklist->count(),
            'passed'       => $checklist->where('is_pass', true)->count(),
            'defects'      => $checklist->where('is_defect', true)->count(),
            'not_answered' => $checklist->where('is_answered', false)->count(),
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->status === InspectionStatus::Submitted;
    }

    /**
     * How many checklist items have been answered, for the "5 / 7 Complete"
     * progress bar at the top of the inspection screen.
     */
    public function completedCount(): int
    {
        return $this->responses
            ->where('status', '!=', InspectionItemStatus::Pending)
            ->count();
    }
}
