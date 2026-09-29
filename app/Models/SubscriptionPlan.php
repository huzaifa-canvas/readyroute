<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'price_amount',
        'billing_period',
        'description',
        'vehicle_limit',
        'driver_limit',
        'trip_limit',
        'is_featured',
    ];

    protected $casts = [
        'is_featured'   => 'boolean',
        'price_amount'  => 'decimal:2',
        'vehicle_limit' => 'integer',
        'driver_limit'  => 'integer',
        'trip_limit'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SubscriptionPlan $plan) {
            if (blank($plan->slug)) {
                $plan->slug = Str::slug($plan->name) ?: 'plan-' . Str::random(6);
            }
        });
    }

    public function companies()
    {
        return $this->hasMany(User::class, 'subscription_plan_id');
    }

    /**
     * A null limit means unlimited, which is how the Enterprise tier is
     * stored — never zero, which would read as "none allowed".
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->{$resource . '_limit'} === null;
    }

    public function limitFor(string $resource): ?int
    {
        return $this->{$resource . '_limit'};
    }

    public function limitLabel(string $resource): string
    {
        return $this->isUnlimited($resource)
            ? 'Unlimited'
            : number_format((int) $this->limitFor($resource));
    }

    /**
     * The price as a sentence, falling back to whatever string the admin
     * typed when there is no numeric amount (an Enterprise "Custom").
     */
    public function priceLabel(): string
    {
        if ($this->price_amount === null) {
            return $this->price ?: 'Custom';
        }

        return '$' . number_format((float) $this->price_amount, 0) . ($this->billing_period ?: '/mo');
    }
}
