<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One subscription payment by one of our companies.
 *
 * Written from the Stripe webhook, never read back from Stripe's account-wide
 * invoice list — that account is shared with other products, so querying it
 * directly counted other people's money as platform revenue.
 */
class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'subscription_plan_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'number',
        'amount',
        'currency',
        'status',
        'issued_at',
        'paid_at',
        'hosted_url',
        'pdf_url',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'issued_at' => 'datetime',
        'paid_at'   => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('dispatcher_id', $companyId);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid'          => 'Paid',
            'open'          => 'Open',
            'draft'         => 'Draft',
            'uncollectible' => 'Uncollectible',
            'void'          => 'Void',
            default         => ucfirst((string) $this->status),
        };
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'paid'          => 'bg-label-success',
            'open'          => 'bg-label-warning',
            'uncollectible' => 'bg-label-danger',
            default         => 'bg-label-secondary',
        };
    }
}
