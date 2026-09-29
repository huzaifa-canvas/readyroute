<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One billed line on an invoice, usually a trip.
 *
 * trip_id is nullable so an invoice can also carry adjustments — a wait-time
 * charge, a credit — that do not correspond to a trip of their own.
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'trip_id',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
