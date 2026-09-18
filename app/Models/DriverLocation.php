<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single GPS fix reported by a driver's device.
 *
 * The table carries only created_at because it is written far more often than
 * anything else in the system and nothing ever updates a row.
 */
class DriverLocation extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'trip_id',
        'lat',
        'lng',
        'speed_mph',
        'heading',
        'accuracy_m',
        'recorded_at',
    ];

    protected $casts = [
        'lat'         => 'decimal:8',
        'lng'         => 'decimal:8',
        'speed_mph'   => 'decimal:2',
        'heading'     => 'decimal:2',
        'accuracy_m'  => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
