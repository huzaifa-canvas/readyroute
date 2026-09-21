<?php

namespace App\Notifications;

/**
 * Shown in the app as "Reminder".
 */
class InspectionReminderNotification extends DriverNotification
{
    public function __construct(private readonly int $minutesUntilDue = 10)
    {
    }

    public function kind(): string
    {
        return 'inspection_reminder';
    }

    public function title(): string
    {
        return 'Reminder';
    }

    public function body(): string
    {
        return sprintf('Pre-trip inspection due in %d mins.', $this->minutesUntilDue);
    }

    public function payload(): array
    {
        return ['screen' => 'inspection'];
    }
}
