<?php

namespace App\Enums;

enum TripStatus: string
{
    case Scheduled       = 'scheduled';
    case EnRoute         = 'en_route';
    case ArrivedPickup   = 'arrived_pickup';
    case InProgress      = 'in_progress';
    case ArrivedDropoff  = 'arrived_dropoff';
    case Completed       = 'completed';
    case Cancelled       = 'cancelled';

    /**
     * Human label as shown in the driver app and dispatcher panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled      => 'Scheduled',
            self::EnRoute        => 'En Route to Pickup',
            self::ArrivedPickup  => 'Arrived at Pickup',
            self::InProgress     => 'Trip Started',
            self::ArrivedDropoff => 'Arrived at Drop-Off',
            self::Completed      => 'Trip Completed',
            self::Cancelled      => 'Cancelled',
        };
    }

    /**
     * Bootstrap badge colour used by the dispatcher panel. Kept beside the
     * labels so a new status cannot be added without deciding how it looks.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Scheduled      => 'bg-label-secondary',
            self::EnRoute        => 'bg-label-info',
            self::ArrivedPickup  => 'bg-label-primary',
            self::InProgress     => 'bg-label-warning',
            self::ArrivedDropoff => 'bg-label-primary',
            self::Completed      => 'bg-label-success',
            self::Cancelled      => 'bg-label-danger',
        };
    }

    /**
     * The button a driver taps to move a trip INTO this status. The app renders
     * whatever comes back here, so the wording lives in one place rather than
     * being duplicated across five screens.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::EnRoute        => 'Confirm Pickup',
            self::ArrivedPickup  => 'Arrived At Pickup',
            self::InProgress     => 'Start Trip',
            self::ArrivedDropoff => 'Arrived At Drop-Off',
            self::Completed      => 'Complete Trip',
            default              => $this->label(),
        };
    }

    /**
     * Short label for the progress stepper along the top of the trip screens.
     */
    public function stepLabel(): string
    {
        return match ($this) {
            self::EnRoute        => 'En Route',
            self::ArrivedPickup  => 'Arrived',
            self::InProgress     => 'Started',
            self::ArrivedDropoff => 'Arrived',
            self::Completed      => 'Finished',
            default              => $this->label(),
        };
    }

    /**
     * Statuses a driver is allowed to set from the mobile app. A driver can
     * move a trip forward through the run, but cannot schedule or cancel one.
     */
    public static function driverSettable(): array
    {
        return [
            self::EnRoute,
            self::ArrivedPickup,
            self::InProgress,
            self::ArrivedDropoff,
            self::Completed,
        ];
    }

    /**
     * Which statuses may directly follow this one. The driver app walks this
     * chain in order; anything else is rejected, so a trip can never be
     * completed without having been started.
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Scheduled      => [self::EnRoute, self::Cancelled],
            self::EnRoute        => [self::ArrivedPickup, self::Cancelled],
            self::ArrivedPickup  => [self::InProgress, self::Cancelled],
            self::InProgress     => [self::ArrivedDropoff, self::Cancelled],
            self::ArrivedDropoff => [self::Completed],
            self::Completed      => [],
            self::Cancelled      => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /**
     * Terminal statuses are never counted as active work.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Statuses that represent a trip currently on a driver's plate.
     */
    public static function activeValues(): array
    {
        return [
            self::Scheduled->value,
            self::EnRoute->value,
            self::ArrivedPickup->value,
            self::InProgress->value,
            self::ArrivedDropoff->value,
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
