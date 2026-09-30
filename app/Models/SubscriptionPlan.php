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
        'features',
        'stripe_price_id',
        'stripe_product_id',
        'billing_interval',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'is_featured'   => 'boolean',
        'is_active'     => 'boolean',
        'features'      => 'array',
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

    /**
     * The intervals Stripe accepts, with how each one reads on a price.
     *
     * @return array<string, array{label: string, suffix: string}>
     */
    public static function intervals(): array
    {
        return [
            'month' => ['label' => 'Monthly',   'suffix' => '/mo'],
            'year'  => ['label' => 'Yearly',    'suffix' => '/yr'],
            'week'  => ['label' => 'Weekly',    'suffix' => '/wk'],
            'day'   => ['label' => 'Daily',     'suffix' => '/day'],
        ];
    }

    public function intervalSuffix(): string
    {
        return static::intervals()[$this->billing_interval ?? 'month']['suffix'] ?? '/mo';
    }

    public function intervalLabel(): string
    {
        return static::intervals()[$this->billing_interval ?? 'month']['label'] ?? 'Monthly';
    }

    /**
     * Is this capability included in the tier?
     *
     * A plan that has never been edited has a null feature set, which means
     * the defaults rather than nothing — a tenant must not lose screens
     * because a column was added underneath them.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features ?? \App\Support\PlanFeatures::defaults();

        return in_array($feature, $features, true);
    }

    /**
     * @return array<int, string>
     */
    public function featureList(): array
    {
        return $this->features ?? \App\Support\PlanFeatures::defaults();
    }

    public function featureCount(): int
    {
        return count($this->featureList());
    }

    /**
     * A plan can only be bought when the admin has linked it to a Stripe
     * price; otherwise it is assign-only.
     */
    public function isPurchasable(): bool
    {
        return $this->is_active && filled($this->stripe_price_id) && $this->price_amount !== null;
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

        return '$' . number_format((float) $this->price_amount, 0) . $this->intervalSuffix();
    }
}
