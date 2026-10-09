<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pre-trip inspection reminder ahead of each driver's first pickup.
Schedule::command('inspections:remind')->everyFiveMinutes()->withoutOverlapping();

// Move companies onto the tier they scheduled once the date has passed, for
// installs the Stripe webhook cannot reach.
Schedule::command('subscriptions:apply-plan-changes')->hourly()->withoutOverlapping();
