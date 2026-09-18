<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'assigned_driver_id',
        'name',
        'vehicle_code',
        'image',
        'make_model_year',
        'year',
        'color',
        'number_plate',
        'status',
        'seating_capacity',
        'wheelchair_ramp',
        'vin_number',
        'maintenance_log_notes',
    ];
    
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('assets/img/illustrations/page-misc-under-maintenance.png'); // Or any default car placeholder
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function assignedDriver()
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }

    public function inspections()
    {
        return $this->hasMany(VehicleInspection::class);
    }

    /**
     * How the vehicle is named to a driver, e.g. "V-204 - Ford Transit".
     */
    public function displayLabel(): string
    {
        $parts = array_filter([
            $this->vehicle_code,
            $this->make_model_year ?: $this->name,
        ]);

        return implode(' - ', $parts);
    }
}
