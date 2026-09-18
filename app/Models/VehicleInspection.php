<?php

namespace App\Models;

use App\Enums\InspectionItemStatus;
use App\Enums\InspectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'driver_id',
        'vehicle_id',
        'inspection_date',
        'status',
        'has_defects',
        'signature_path',
        'submitted_at',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'status'          => InspectionStatus::class,
        'has_defects'     => 'boolean',
        'submitted_at'    => 'datetime',
    ];

    protected $hidden = [
        'signature_path',
    ];

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function responses()
    {
        return $this->hasMany(InspectionResponse::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === InspectionStatus::Submitted;
    }

    /**
     * How many checklist items have been answered, for the "5 / 7 Complete"
     * progress bar at the top of the inspection screen.
     */
    public function completedCount(): int
    {
        return $this->responses
            ->where('status', '!=', InspectionItemStatus::Pending)
            ->count();
    }
}
