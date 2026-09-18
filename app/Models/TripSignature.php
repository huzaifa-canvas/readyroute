<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TripSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'signer_role',
        'signer_name',
        'signature_path',
        'signed_at',
        'ip_address',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    protected $hidden = [
        // The raw path is never exposed; callers use temporaryUrl() instead.
        'signature_path',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * A short-lived signed URL for the stored image. Signatures sit on a
     * private disk because they are patient-linked medical transport records,
     * so there is no permanent public URL to hand out.
     */
    public function temporaryUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $disk = config('readyroute.signatures.disk');
        $ttl  = (int) config('readyroute.signatures.url_ttl_minutes', 15);

        return Storage::disk($disk)->temporaryUrl(
            $this->signature_path,
            now()->addMinutes($ttl)
        );
    }
}
