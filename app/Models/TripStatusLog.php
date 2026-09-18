<?php

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'status',
        'lat',
        'lng',
        'logged_at',
    ];

    protected $casts = [
        'status'    => TripStatus::class,
        'lat'       => 'decimal:8',
        'lng'       => 'decimal:8',
        'logged_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
