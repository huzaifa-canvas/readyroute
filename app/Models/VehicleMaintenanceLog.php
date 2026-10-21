<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One job on one vehicle: a service, a repair, a booking that has not happened
 * yet.
 */
class VehicleMaintenanceLog extends Model
{
    use HasFactory;

    /**
     * The kinds of work, and what each is called on screen.
     */
    public const TYPES = [
        'service'    => 'Routine service',
        'repair'     => 'Repair',
        'inspection' => 'Safety inspection',
        'tyres'      => 'Tyres',
        'bodywork'   => 'Bodywork',
        'cleaning'   => 'Deep clean',
        'defect'     => 'Inspection defect',
        'other'      => 'Other',
    ];

    /**
     * Work still owed, then history. Nothing moves backwards on its own.
     */
    public const STATUSES = [
        'scheduled'   => 'Scheduled',
        'in_progress' => 'In progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];

    protected $fillable = [
        'dispatcher_id',
        'vehicle_id',
        'created_by',
        'type',
        'status',
        'title',
        'description',
        'vendor',
        'cost',
        'odometer',
        'performed_on',
        'due_on',
        'completed_at',
        'vehicle_inspection_id',
    ];

    protected $casts = [
        'cost'         => 'decimal:2',
        'odometer'     => 'integer',
        'performed_on' => 'date',
        'due_on'       => 'date',
        'completed_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inspection()
    {
        return $this->belongsTo(VehicleInspection::class, 'vehicle_inspection_id');
    }

    public function scopeForDispatcher(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('dispatcher_id', $companyId);
    }

    /**
     * Work that has not been finished — what takes a vehicle off the road.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'in_progress']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['scheduled', 'in_progress'], true);
    }

    /**
     * Booked for a date that has already passed, and still not done.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'scheduled'
            && $this->due_on !== null
            && $this->due_on->isPast();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'completed'   => 'bg-label-success',
            'in_progress' => 'bg-label-warning',
            'scheduled'   => $this->isOverdue() ? 'bg-label-danger' : 'bg-label-info',
            default       => 'bg-label-secondary',
        };
    }

    /**
     * The date this row belongs under: when it was done, or when it is due.
     */
    public function onDate(): ?\Illuminate\Support\Carbon
    {
        return $this->performed_on ?: $this->due_on;
    }
}
