<?php

namespace App\Services;

use App\Enums\InspectionItemStatus;
use App\Enums\InspectionStatus;
use App\Models\InspectionItem;
use App\Models\InspectionResponse;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Notifications\InspectionDefectNotification;
use Illuminate\Database\UniqueConstraintViolationException;
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
    public function __construct(
        private readonly SignatureService $signatures,
        private readonly SocketEmitter $socket,
    ) {
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

        $keys = [
            'driver_id'       => $driver->id,
            'vehicle_id'      => $vehicle->id,
            'inspection_date' => $date->toDateString(),
        ];

        try {
            $inspection = VehicleInspection::firstOrCreate($keys, [
                'dispatcher_id' => $driver->dispatcher_id ?: $vehicle->dispatcher_id,
                'status'        => InspectionStatus::Draft->value,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Another request created today's inspection between our check and
            // our insert. That is the row we wanted, so take it.
            $inspection = VehicleInspection::where($keys)->firstOrFail();
        }

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

        if ($items->isEmpty()) {
            return;
        }

        $now = now();

        $rows = $items->map(fn ($item) => [
            'vehicle_inspection_id' => $inspection->id,
            'inspection_item_id'    => $item->id,
            'status'                => InspectionItemStatus::Pending->value,
            'created_at'            => $now,
            'updated_at'            => $now,
        ])->all();

        /*
         * One statement, and the database decides.
         *
         * Reading the existing ids and inserting what looked missing was a
         * read-then-write race: two requests arriving together — a retried
         * tap, or the app opening the checklist twice — both saw the same row
         * missing and both inserted it, which the unique index then rejected
         * and the driver saw as a 422.
         *
         * insertOrIgnore leaves rows that already exist untouched, so answers
         * already given are never disturbed and running this on every open
         * stays safe.
         */
        InspectionResponse::insertOrIgnore($rows);
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

        $inspection = DB::transaction(function () use ($inspection, $path) {
            $previous = $inspection->signature_path;

            $inspection->forceFill([
                'status'         => InspectionStatus::Submitted->value,
                'signature_path' => $path,
                'submitted_at'   => now(),
            ])->save();

            if ($previous && $previous !== $path) {
                $this->signatures->delete($previous);
            }

            return $inspection->fresh(['responses.item', 'vehicle', 'driver']);
        });

        if ($inspection->has_defects) {
            $this->alertDefects($inspection);
        }

        return $inspection;
    }

    /**
     * Tell the office about a vehicle signed off with defects: a socket event
     * for anyone with the panel open, and a notification for everyone else.
     * Runs after the commit, so a failed alert never undoes the inspection.
     */
    private function alertDefects(VehicleInspection $inspection): void
    {
        $companyId = $inspection->dispatcher_id;

        $recipients = User::query()
            ->where('id', $companyId)
            ->orWhere(fn ($q) => $q->where('dispatcher_id', $companyId)->where('role', 'dispatcher'))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new InspectionDefectNotification($inspection));
        }

        $payload = [
            'kind'          => 'inspection_defect',
            'inspection_id' => $inspection->id,
            'driver_id'     => $inspection->driver_id,
            'vehicle_id'    => $inspection->vehicle_id,
        ];

        $this->socket->queue(SocketEmitter::dispatcherRoom($companyId), 'notification:new', $payload);
        $this->socket->queue(SocketEmitter::userRoom($companyId), 'notification:new', $payload);
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
     * panel can use this to see who has not inspected yet, and the trip
     * lifecycle uses it to hold a driver back until they have.
     */
    public function isClearedToday(User $driver, ?int $vehicleId = null): bool
    {
        $vehicleId ??= $driver->assignedVehicle?->id;

        if (! $vehicleId) {
            return false;
        }

        return VehicleInspection::where('driver_id', $driver->id)
            ->where('vehicle_id', $vehicleId)
            ->whereDate('inspection_date', now()->toDateString())
            ->where('status', InspectionStatus::Submitted->value)
            ->exists();
    }

    /**
     * Refuse to let a driver set off before today's inspection is signed.
     *
     * The trip's own vehicle is checked when it has one, since that is what
     * the driver is about to drive; otherwise the driver's assigned vehicle.
     *
     * @throws RuntimeException
     */
    public function ensureClearedFor(User $driver, ?int $vehicleId = null): void
    {
        if (! config('readyroute.inspection.required_before_trip')) {
            return;
        }

        if (! $this->isClearedToday($driver, $vehicleId)) {
            throw new RuntimeException(
                "Please complete and sign today's pre-trip inspection before starting this trip."
            );
        }
    }

    public function vehicleFor(User $driver): ?Vehicle
    {
        return $driver->assignedVehicle;
    }
}
