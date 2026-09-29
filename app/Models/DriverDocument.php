<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A document a driver has to keep current — licence, medical card, insurance.
 *
 * status is stored rather than derived so a list can be filtered and sorted in
 * SQL, but it is always recomputed from expires_on when a row is written, so
 * the column can never drift from the date it describes. A document that has
 * gone stale in the table is refreshed by the compliance screens on read.
 */
class DriverDocument extends Model
{
    use HasFactory;

    /** A document is "expiring" once it falls inside this window. */
    public const EXPIRY_WARNING_DAYS = 30;

    public const TYPES = [
        'drivers_license'  => "Driver's Licence",
        'medical_card'     => 'Medical Card',
        'insurance'        => 'Insurance',
        'background_check' => 'Background Check',
        'training'         => 'Training Certificate',
        'other'            => 'Other',
    ];

    protected $fillable = [
        'dispatcher_id',
        'driver_id',
        'type',
        'label',
        'reference',
        'issued_on',
        'expires_on',
        'file_path',
        'status',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'issued_on'  => 'date',
        'expires_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (DriverDocument $document) {
            $document->status = $document->deriveStatus();
        });
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * What the status column would be for the current expiry date.
     */
    public function deriveStatus(): string
    {
        if (! $this->expires_on) {
            // No expiry means nothing to chase — a training certificate, say.
            return 'valid';
        }

        $expiry = Carbon::parse($this->expires_on)->endOfDay();

        if ($expiry->isPast()) {
            return 'expired';
        }

        return $expiry->lessThanOrEqualTo(now()->addDays(self::EXPIRY_WARNING_DAYS))
            ? 'expiring'
            : 'valid';
    }

    public function scopeForDispatcher(Builder $query, int $dispatcherId): Builder
    {
        return $query->where('dispatcher_id', $dispatcherId);
    }

    /**
     * Anything that needs attention, newest deadline first.
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', now()->addDays(self::EXPIRY_WARNING_DAYS))
            ->orderBy('expires_on');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_on')
            ->whereDate('expires_on', '<', now());
    }

    public function isExpired(): bool
    {
        return $this->deriveStatus() === 'expired';
    }

    public function isExpiring(): bool
    {
        return $this->deriveStatus() === 'expiring';
    }

    public function typeLabel(): string
    {
        return $this->label ?: (self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type)));
    }

    public function statusLabel(): string
    {
        return match ($this->deriveStatus()) {
            'expired'  => 'Expired',
            'expiring' => 'Expiring soon',
            default    => 'Valid',
        };
    }

    public function statusClass(): string
    {
        return match ($this->deriveStatus()) {
            'expired'  => 'bg-label-danger',
            'expiring' => 'bg-label-warning',
            default    => 'bg-label-success',
        };
    }

    /**
     * Whole days until expiry; negative once it has passed, null when the
     * document never expires.
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_on) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($this->expires_on)->startOfDay(),
            false
        );
    }
}
