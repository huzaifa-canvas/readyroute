<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'req_wheelchair' => 'boolean',
        'req_stretcher' => 'boolean',
        'req_o2_tank' => 'boolean',
        'req_bariatric' => 'boolean',
        'req_no_steps' => 'boolean',
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
}
