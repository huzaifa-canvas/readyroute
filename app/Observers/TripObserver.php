<?php

namespace App\Observers;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripAssignedNotification;
use App\Notifications\TripCancelledNotification;
use App\Services\SocketEmitter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells a driver when a trip lands on their plate or is taken off it.
 *
 * This lives on the model rather than in the controllers because a trip gets a
 * driver from four different places — the create form, the edit form, the
 * "Assign" button on the dispatch board and the round-robin in auto-dispatch —
 * and the driver has to be told every time. Hanging the rule off the save
 * itself means a fifth path cannot be added that quietly skips the
 * notification.
 */
class TripObserver
{
    public function __construct(private readonly SocketEmitter $socket)
    {
    }

    /**
     * Stamp when a trip was cancelled, wherever the cancellation came from.
     *
     * Runs on save rather than in the cancel action so the timestamp is
     * correct even when a dispatcher sets the status from the edit form.
     */
    public function saving(Trip $trip): void
    {
        if ($trip->statusEnum() === TripStatus::Cancelled) {
            $trip->cancelled_at = $trip->cancelled_at ?: now();

            return;
        }

        // A dispatcher can put a cancelled trip back into service from the
        // edit form. The stamp has to go with it, or the trip would read as
        // cancelled on a date it was running.
        if ($trip->cancelled_at) {
            $trip->cancelled_at = null;
        }
    }

    public function created(Trip $trip): void
    {
        if ($trip->statusEnum()?->isTerminal()) {
            return;
        }

        $this->tellDriver($trip, new TripAssignedNotification($trip), 'trip:assigned');
    }

    public function updated(Trip $trip): void
    {
        // Cancellation wins over everything else. A trip that is cancelled and
        // reassigned in the same save is still cancelled, and announcing it as
        // new work would be worse than saying nothing.
        if ($trip->wasChanged('status') && $trip->statusEnum() === TripStatus::Cancelled) {
            $this->tellDriver($trip, new TripCancelledNotification($trip), 'trip:cancelled');

            return;
        }

        if (! $trip->wasChanged('driver_id') || $trip->statusEnum()?->isTerminal()) {
            return;
        }

        $this->tellDriver($trip, new TripAssignedNotification($trip), 'trip:assigned');
    }

    /**
     * A trip deleted off the panel is still work taken off a driver.
     *
     * They are told the same way a cancellation tells them, because from the
     * cab the two are the same news: the job is off. Saying nothing would
     * leave them waiting at a pickup for a trip that no longer exists.
     */
    public function deleted(Trip $trip): void
    {
        if ($trip->statusEnum()?->isTerminal()) {
            return;
        }

        $this->tellDriver($trip, new TripCancelledNotification($trip), 'trip:cancelled');
    }

    /**
     * Write the notification and nudge the app over the socket.
     *
     * The database notification is what the driver's list reads, so it is the
     * part that must not fail; the socket emit only saves the app a poll. A
     * trip with no driver on it has nobody to tell.
     */
    private function tellDriver(Trip $trip, object $notification, string $event): void
    {
        if (! $trip->driver_id) {
            return;
        }

        $driver = $trip->relationLoaded('driver')
            ? $trip->driver
            : User::find($trip->driver_id);

        if (! $driver) {
            return;
        }

        $driver->notify($notification);

        try {
            $this->socket->queue(
                SocketEmitter::userRoom($driver->id),
                $event,
                [
                    'trip_id'   => $trip->id,
                    'reference' => $trip->reference(),
                    'status'    => $trip->statusEnum()?->value,
                ]
            );
        } catch (Throwable $e) {
            // The driver already has the notification in the database; a
            // socket server that is down must not fail the dispatcher's save.
            Log::warning('Trip socket notice failed', [
                'trip_id' => $trip->id,
                'event'   => $event,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
