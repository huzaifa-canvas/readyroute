<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One invoice or claim sent to a payer.
 *
 * "Claim" and "invoice" are the same record here: a claim is simply an invoice
 * whose payer is a broker or a state programme, which is why the payer type
 * rather than a separate table carries the distinction.
 */
class Invoice extends Model
{
    use HasFactory;

    public const PAYER_TYPES = [
        'medicaid'  => 'Medicaid',
        'medicare'  => 'Medicare',
        'broker'    => 'Broker',
        'insurance' => 'Insurance',
        'private'   => 'Private Pay',
        'other'     => 'Other',
    ];

    public const STATUSES = [
        'draft'      => 'Draft',
        'submitted'  => 'Submitted',
        'processing' => 'Processing',
        'paid'       => 'Paid',
        'rejected'   => 'Rejected',
    ];

    protected $fillable = [
        'dispatcher_id',
        'number',
        'payer_name',
        'payer_type',
        'payer_reference',
        'issued_on',
        'due_on',
        'submitted_at',
        'paid_at',
        'status',
        'amount',
        'rejection_reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'issued_on'    => 'date',
        'due_on'       => 'date',
        'submitted_at' => 'datetime',
        'paid_at'      => 'datetime',
        'amount'       => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForDispatcher(Builder $query, int $dispatcherId): Builder
    {
        return $query->where('dispatcher_id', $dispatcherId);
    }

    /**
     * Money that has been billed but not yet collected — the outstanding
     * receivable. A draft has not been sent, so it does not count.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'processing']);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * The next reference for a company: INV-2026-0001, restarting each year so
     * numbers stay short and sortable.
     */
    public static function nextNumber(int $dispatcherId): string
    {
        $year   = now()->format('Y');
        $prefix = 'INV-' . $year . '-';

        $last = static::where('dispatcher_id', $dispatcherId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = $last
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recalculate the cached total from the lines.
     */
    public function refreshTotal(): void
    {
        $this->forceFill(['amount' => (float) $this->items()->sum('amount')])->save();
    }

    public function isEditable(): bool
    {
        // Once money has been collected the record stops being a working
        // document; a rejection is still editable so it can be resubmitted.
        return $this->status !== 'paid';
    }

    public function isOutstanding(): bool
    {
        return in_array($this->status, ['submitted', 'processing'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOutstanding()
            && $this->due_on !== null
            && $this->due_on->isPast();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'paid'       => 'bg-label-success',
            'processing' => 'bg-label-info',
            'submitted'  => 'bg-label-primary',
            'rejected'   => 'bg-label-danger',
            default      => 'bg-label-secondary',
        };
    }

    public function payerTypeLabel(): string
    {
        return self::PAYER_TYPES[$this->payer_type] ?? ucfirst((string) $this->payer_type);
    }
}
