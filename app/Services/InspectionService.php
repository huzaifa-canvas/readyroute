<?php

namespace App\Services;

use App\Enums\InspectionItemStatus;
use App\Enums\InspectionStatus;
use App\Models\InspectionItem;
use App\Models\InspectionResponse;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Daily vehicle inspections (DVIR).
 *
 * The checklist is owned per dispatcher company rather than hard-coded: fleets
 * differ, and a company with no wheelchair vans should not be asked to inspect
 * a wheelchair lift.
 */
class InspectionService
{
    public function __construct(private readonly SignatureService $signatures)
    {
    }

    /**
     * Today's inspection for this driver, created as a draft on first open so
     * the app never has to decide whether to create or fetch.
     *
     * @throws RuntimeException when the driver has no vehicle to inspect
     */
    public function todayFor(User $driver, ?Carbon $date = null): VehicleInspection
    {
        $date ??= now();

        $vehicle = $driver->assignedVehicle;

        if (! $vehicle) {
            throw new RuntimeException(
                'No vehicle is assigned to you yet. Please contact your dispatcher.'
            );
        }

        $inspection = VehicleInspection::firstOrCreate(
            [
                'driver_id'       => $driver->id,
                'vehicle_id'      => $vehicle->id,
                'inspection_date' => $date->toDateString(),
            ],
            [
                'dispatcher_id' => $driver->dispatcher_id ?: $vehicle->dispatcher_id,
                'status'        => InspectionStatus::Draft->value,
            ]
        );

        $this->syncResponses($inspection);

        return $inspection->load(['responses.item', 'vehicle']);
    }

    /**
     * Make sure the inspection has a row for every active checklist item.
     *
     * Running this on each open means an item the dispatcher added this morning
     * appears in a checklist the driver opened earlier, without disturbing
     * answers already given.
     */
    private function syncResponses(VehicleInspection $inspection): void
    {
        if ($inspection->isSubmitted()) {
            return;
        }

        $items = $this->checklistFor($inspection->dispatcher_id);

        $existing = InspectionResponse::where('vehicle_inspection_id', $inspection->id)
            ->pluck('inspection_item_id')
            ->all();

        $missing = $items->reject(fn ($item) => in_array($item->id, $existing, true));

        foreach ($missing as $item) {
            InspectionResponse::create([
                'vehicle_inspection_id' => $inspection->id,
                'inspection_item_id'    => $item->id,
                'status'                => InspectionItemStatus::Pending->value,
            ]);
        }
    }

    /**
     * The company's active checklist, seeding the defaults the first time a
     * company is seen so no driver ever opens an empty inspection.
     */
    public function checklistFor(int $dispatcherId)
    {
        $items = InspectionItem::where('dispatcher_id', $dispatcherId)
            ->active()
            ->ordered()
            ->get();

        if ($items->isEmpty()) {
            $dispatcher = User::find($dispatcherId);

            if ($dispatcher) {
                InspectionItem::seedDefaultsFor($dispatcher);

                $items = InspectionItem::where('dispatcher_id', $dispatcherId)
                    ->active()
                    ->ordered()
                    ->get();
            }
        }

        return $items;
    }

    /**
     * Record the driver's answer for one checklist item.
     *
     * @throws RuntimeException
     */
    public function answer(
        VehicleInspection $inspection,
        int $itemId,
        InspectionItemStatus $status,
        ?string $note = null,
    ): InspectionResponse {
        if ($inspection->isSubmitted()) {
            throw new RuntimeException('This inspection has already been submitted and cannot be changed.');
        }

        $response = InspectionResponse::where('vehicle_inspection_id', $inspection->id)
            ->where('inspection_item_id', $itemId)
            ->first();

        if (! $response) {
            throw new RuntimeException('That item is not part of this inspection.');
        }

        // A defect without a description is not actionable for the dispatcher
        // or for whoever services the vehicle.
        if ($status === InspectionItemStatus::Fail && blank($note)) {
            throw new RuntimeException('Please describe the problem when you mark an item as a defect.');
        }

        $response->status     = $status->value;
        $response->note       = $status === InspectionItemStatus::Fail ? $note : ($note ?: null);
        $response->checked_at = $status === InspectionItemStatus::Pending ? null : now();
        $response->save();

        $this->refreshDefectFlag($inspection);

        return $response->load('item');
    }

    private function refreshDefectFlag(VehicleInspection $inspection): void
    {
        $hasDefects = InspectionResponse::where('vehicle_inspection_id', $inspection->id)
            ->where('status', InspectionItemStatus::Fail->value)
            ->exists();

        if ($inspection->has_defects !== $hasDefects) {
            $inspection->forceFill(['has_defects' => $hasDefects])->save();
        }
    }

    /**
     * Close out the inspection with the driver's signature.
     *
     * @throws RuntimeException
     */
    public function submit(VehicleInspection $inspection, string $signaturePayload): VehicleInspection
    {
        if ($inspection->isSubmitted()) {
            throw new RuntimeException('This inspection has already been submitted.');
        }

        $inspection->load('responses.item');

        $outstanding = $inspection->responses
            ->filter(fn (InspectionResponse $r) => $r->item?->is_required && ! $r->isAnswered())
            ->map(fn (InspectionResponse $r) => $r->item->label)
            ->values();

        if ($outstanding->isNotEmpty()) {
            throw new RuntimeException(
                'Please check every required item first: ' . $outstanding->implode(', ') . '.'
            );
        }

        $path = $this->signatures->storeImage($signaturePayload, 'inspection-' . $inspection->id);

        return DB::transaction(function () use ($inspection, $path) {
            $previous = $inspection->signature_path;

            $inspection->forceFill([
                'status'         => InspectionStatus::Submitted->value,
                'signature_path' => $path,
                'submitted_at'   => now(),
            ])->save();

            if ($previous && $previous !== $path) {
                $this->signatures->delete($previous);
            }

            return $inspection->fresh(['responses.item', 'vehicle']);
        });
    }

    /**
     * Progress for the "5 / 7 Complete" bar.
     */
    public function progress(VehicleInspection $inspection): array
    {
        $total     = $inspection->responses->count();
        $completed = $inspection->completedCount();

        return [
            'completed'  => $completed,
            'total'      => $total,
            'percent'    => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'is_ready'   => $this->outstandingRequired($inspection)->isEmpty(),
            'outstanding' => $this->outstandingRequired($inspection)->values(),
        ];
    }

    private function outstandingRequired(VehicleInspection $inspection)
    {
        return $inspection->responses
            ->filter(fn (InspectionResponse $r) => $r->item?->is_required && ! $r->isAnswered())
            ->map(fn (InspectionResponse $r) => $r->item->label);
    }

    /**
     * Whether a driver has cleared the vehicle for the day. The dispatcher
     * panel can use this to see who has not inspected yet.
     */
    public function isClearedToday(User $driver): bool
    {
        $vehicle = $driver->assignedVehicle;

        if (! $vehicle) {
            return false;
        }

        return VehicleInspection::where('driver_id', $driver->id)
            ->where('vehicle_id', $vehicle->id)
            ->whereDate('inspection_date', now()->toDateString())
            ->where('status', InspectionStatus::Submitted->value)
            ->exists();
    }

    public function vehicleFor(User $driver): ?Vehicle
    {
        return $driver->assignedVehicle;
    }
}
