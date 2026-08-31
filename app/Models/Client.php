<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'full_name',
        'dob',
        'phone_number',
        'email',
        'home_address',
        'apt_unit',
        'city',
        'zip_code',
        'funding_type',
        'insurance_id',
        'wheelchair_required',
        'ambulatory_assistance',
        'stretcher_transport',
        'bariatric_vehicle',
        'emergency_contact_name',
        'emergency_contact_phone',
        'special_notes',
    ];

    protected $casts = [
        'dob' => 'date',
        'wheelchair_required' => 'boolean',
        'ambulatory_assistance' => 'boolean',
        'stretcher_transport' => 'boolean',
        'bariatric_vehicle' => 'boolean',
    ];
    
    protected $appends = ['age'];

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function getAgeAttribute()
    {
        if ($this->dob) {
            return Carbon::parse($this->dob)->age;
        }
        return null;
    }
}
