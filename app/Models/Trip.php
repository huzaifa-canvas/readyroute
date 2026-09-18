<?php

namespace App\Models;

use App\Enums\ConfirmationStatus;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'client_id',
        'first_name',
        'last_name',
        'phone_number',
        'member_id',
        'req_wheelchair',
        'req_stretcher',
        'req_o2_tank',
        'req_bariatric',
        'req_no_steps',
        'pickup_date',
        'pickup_time',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'distance',
        'trip_type',
        'notes',
        'driver_id',
        'vehicle_id',
        'billing_type',
        'status',
        'confirmation_status',
        'confirmed_at',
        'confirmed_via',
        'en_route_at',
        'arrived_pickup_at',
        'started_at',
        'arrived_dropoff_at',
        'completed_at',
        'actual_distance',
        'actual_duration_min',
        'was_on_time',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'req_wheelchair' => 'boolean',
        'req_stretcher' => 'boolean',
        'req_o2_tank' => 'boolean',
        'req_bariatric' => 'boolean',
        'req_no_steps' => 'boolean',
        // NOTE: "status" is deliberately left as a plain string rather than
        // cast to TripStatus. The dispatcher panel compares it against string
        // literals and passes it to str_replace() in its Blade views, all of
        // which break against an enum instance. New code reads it through
        // statusEnum() instead; the cast is added when the panel is migrated.
        'confirmation_status' => ConfirmationStatus::class,
        'confirmed_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_pickup_at' => 'datetime',
        'started_at' => 'datetime',
        'arrived_dropoff_at' => 'datetime',
        'completed_at' => 'datetime',
        'actual_distance' => 'decimal:2',
        'actual_duration_min' => 'integer',
        'was_on_time' => 'boolean',
    ];

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(TripStatusLog::class)->orderBy('logged_at');
    }

    public function signature()
    {
        return $this->hasOne(TripSignature::class)->where('signer_role', 'driver');
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class);
    }

    /**
     * The status as a typed value, which is how all driver-app code should
     * read it. Returns null if the column ever holds something unrecognised.
     */
    public function statusEnum(): ?TripStatus
    {
        return $this->status ? TripStatus::tryFrom($this->status) : null;
    }

    public function isStatus(TripStatus $status): bool
    {
        return $this->status === $status->value;
    }

    /**
     * The single status a driver can move this trip to next, or null when the
     * run is finished. Cancellation is excluded because only a dispatcher may
     * cancel a trip.
     */
    public function nextDriverStatus(): ?TripStatus
    {
        $current = $this->statusEnum();

        if (! $current) {
            return null;
        }

        foreach ($current->allowedNext() as $next) {
            if ($next !== TripStatus::Cancelled) {
                return $next;
            }
        }

        return null;
    }

    /**
     * The mobility and equipment needs flagged on this trip, as labels the
     * driver can read. Only the ones that apply are returned.
     */
    public function requirementLabels(): array
    {
        $map = [
            'req_wheelchair' => 'Wheelchair',
            'req_stretcher'  => 'Stretcher',
            'req_o2_tank'    => 'Oxygen Tank',
            'req_bariatric'  => 'Bariatric Vehicle',
            'req_no_steps'   => 'No Steps',
        ];

        $labels = [];

        foreach ($map as $field => $label) {
            if ($this->{$field}) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Trips belonging to one driver. Every driver-facing query goes through
     * this so a driver can never read another company's patient records.
     */
    public function scopeForDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TripStatus::activeValues());
    }

    /**
     * The scheduled pickup as a single point in time. pickup_date and
     * pickup_time are stored separately, so they are combined here rather than
     * in every caller.
     */
    public function scheduledPickupAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->pickup_date || ! $this->pickup_time) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse(
            $this->pickup_date->format('Y-m-d') . ' ' . $this->pickup_time
        );
    }

    /**
     * Whether the driver reached the pickup within the configured grace period.
     * Returns null when the trip never reached pickup, so unfinished and
     * cancelled trips stay out of the on-time rate entirely.
     */
    public function resolveOnTime(): ?bool
    {
        $scheduled = $this->scheduledPickupAt();

        if (! $scheduled || ! $this->arrived_pickup_at) {
            return null;
        }

        $grace = (int) config('readyroute.on_time_grace_minutes', 10);

        return $this->arrived_pickup_at->lte($scheduled->copy()->addMinutes($grace));
    }

    public function passengerName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * The trip reference shown to drivers, e.g. #1041.
     */
    public function reference(): string
    {
        return '#' . $this->id;
    }
}
