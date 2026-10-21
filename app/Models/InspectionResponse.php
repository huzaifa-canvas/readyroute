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

    /**
     * Whether the driver reported a fault on this item.
     *
     * Here rather than compared against a string at each call site: the enum
     * case is `fail`, and code that guessed `defect` compiled perfectly and
     * quietly reported that nothing was ever wrong.
     */
    public function isDefect(): bool
    {
        return $this->status === InspectionItemStatus::Fail;
    }

    public function isPass(): bool
    {
        return $this->status === InspectionItemStatus::Pass;
    }

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
