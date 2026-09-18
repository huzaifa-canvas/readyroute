<?php

namespace App\Models;

use App\Enums\InspectionItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_inspection_id',
        'inspection_item_id',
        'status',
        'note',
        'checked_at',
    ];

    protected $casts = [
        'status'     => InspectionItemStatus::class,
        'checked_at' => 'datetime',
    ];

    public function inspection()
    {
        return $this->belongsTo(VehicleInspection::class, 'vehicle_inspection_id');
    }

    public function item()
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function isAnswered(): bool
    {
        return $this->status !== InspectionItemStatus::Pending;
    }
}
