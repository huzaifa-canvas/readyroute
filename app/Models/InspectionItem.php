<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single line on a dispatcher company's DVIR checklist template.
 */
class InspectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'label',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * The checklist every new dispatcher company starts with. Companies can
     * add, retire or reorder these from the web panel.
     */
    public const DEFAULTS = [
        'Brakes & Parking Brake',
        'Headlights & Turn Signals',
        'Tire Pressure & Tread',
        'Wheelchair Lift & Restraints',
        'Fuel Level (>= 1/4 Tank)',
        'Interior Cleanliness',
        'First-Aid Kit & Extinguisher',
    ];

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function responses()
    {
        return $this->hasMany(InspectionResponse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Give a dispatcher company the default checklist. Safe to call more than
     * once: it does nothing if the company already has items.
     */
    public static function seedDefaultsFor(User $dispatcher): void
    {
        if (static::where('dispatcher_id', $dispatcher->id)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $index => $label) {
            static::create([
                'dispatcher_id' => $dispatcher->id,
                'label'         => $label,
                'sort_order'    => $index + 1,
                'is_required'   => true,
                'is_active'     => true,
            ]);
        }
    }
}
