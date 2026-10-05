<?php

namespace App\Console\Commands;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Notifications\InspectionReminderNotification;
use App\Services\InspectionService;
use Illuminate\Console\Command;

/**
 * Nudge drivers whose first pickup of the day is close and who have not yet
 * signed today's pre-trip inspection.
 *
 * Scheduled every five minutes. Each driver gets at most one reminder a day,
 * checked against the notifications already sent, so running it more often
 * or by hand never repeats one.
 */
class SendInspectionReminders extends Command
{
    protected $signature = 'inspections:remind';

    protected $description = 'Remind drivers to complete their pre-trip inspection before the first pickup';

    public function handle(InspectionService $inspections): int
    {
        $window = (int) config('readyroute.inspection.reminder_minutes_before', 30);
        $now    = now();
        $until  = $now->copy()->addMinutes($window);

        // Today's trips still waiting to set off, earliest first, so the first
        // one seen per driver is the pickup the inspection has to be ready for.
        $nextTrips = Trip::query()
            ->with('driver')
            ->whereNotNull('driver_id')
            ->whereDate('pickup_date', $now->toDateString())
            ->where('status', TripStatus::Scheduled->value)
            ->orderBy('pickup_time')
            ->get()
            ->filter(fn (Trip $trip) => $trip->scheduledPickupAt()?->gte($now))
            ->unique('driver_id');

        $sent = 0;

        foreach ($nextTrips as $trip) {
            $driver = $trip->driver;
            $pickup = $trip->scheduledPickupAt();

            if (! $driver || $pickup->gt($until)) {
                continue;
            }

            if ($inspections->isClearedToday($driver, $trip->vehicle_id)) {
                continue;
            }

            $alreadyReminded = $driver->notifications()
                ->where('type', InspectionReminderNotification::class)
                ->whereDate('created_at', $now->toDateString())
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            $driver->notify(new InspectionReminderNotification(
                max(1, (int) ceil($now->diffInSeconds($pickup) / 60))
            ));

            $sent++;
        }

        $this->info("Sent {$sent} inspection " . ($sent === 1 ? 'reminder' : 'reminders') . '.');

        return self::SUCCESS;
    }
}
