<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something that went wrong on the road — reported by the driver, worked
 * through by the dispatcher.
 *
 * An SOS is the same record with type 'sos' and severity 'critical': the
 * driver taps once and the row is written from whatever the phone already
 * knows, so the dispatcher hears about it before any description exists.
 */
class TripIncident extends Model
{
    use HasFactory;

    public const TYPES = [
        'sos'       => 'SOS / Emergency',
        'accident'  => 'Accident',
        'vehicle'   => 'Vehicle problem',
        'passenger' => 'Passenger issue',
        'delay'     => 'Delay or obstruction',
        'other'     => 'Other',
    ];

    public const SEVERITIES = [
        'low'      => 'Low',
        'medium'   => 'Medium',
        'high'     => 'High',
        'critical' => 'Critical',
    ];

    public const STATUSES = [
        'open'         => 'Open',
        'acknowledged' => 'Acknowledged',
        'resolved'     => 'Resolved',
    ];

    protected $fillable = [
        'dispatcher_id',
        'driver_id',
        'trip_id',
        'vehicle_id',
        'type',
        'severity',
        'title',
        'description',
        'lat',
        'lng',
        'address',
        'attachments',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'attachments'     => 'array',
        'lat'             => 'float',
        'lng'             => 'float',
        'acknowledged_at' => 'datetime',
        'resolved_at'     => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'acknowledged']);
    }

    public function scopeForDispatcher(Builder $query, int $dispatcherId): Builder
    {
        return $query->where('dispatcher_id', $dispatcherId);
    }

    public function isSos(): bool
    {
        return $this->type === 'sos';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function severityLabel(): string
    {
        return self::SEVERITIES[$this->severity] ?? ucfirst((string) $this->severity);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Vuexy badge class for the severity pill.
     */
    public function severityClass(): string
    {
        return match ($this->severity) {
            'critical' => 'bg-label-danger',
            'high'     => 'bg-label-warning',
            'low'      => 'bg-label-secondary',
            default    => 'bg-label-info',
        };
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'resolved'     => 'bg-label-success',
            'acknowledged' => 'bg-label-info',
            default        => 'bg-label-danger',
        };
    }

    /**
     * A short line for list rows, since title is optional on an SOS.
     */
    public function headline(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        return $this->isSos()
            ? 'Emergency alert'
            : $this->typeLabel();
    }
}
