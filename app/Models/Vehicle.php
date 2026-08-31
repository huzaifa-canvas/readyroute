<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'name',
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
}
