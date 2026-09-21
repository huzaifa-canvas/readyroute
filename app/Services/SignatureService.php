<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores trip sign-off signatures.
 *
 * A signature is a patient-linked medical transport record, so the image never
 * touches the public disk and is only ever handed out as a short-lived signed
 * URL. Incoming data is decoded and inspected before anything is written: the
 * app sends base64, and base64 can carry anything.
 */
class SignatureService
{
    /**
     * Formats a signature pad can legitimately produce.
     */
    private const ALLOWED_MIME = ['image/png', 'image/jpeg'];

    /**
     * Decode, verify and store a signature image, returning its path on the
     * private disk. Shared by trip sign-off and pre-trip inspections so both
     * go through exactly the same checks.
     *
     * @throws RuntimeException when the payload is not a usable signature
     */
    public function storeImage(string $payload, string $folder): string
    {
        $binary = $this->decode($payload);

        $this->guardSize($binary);
        $extension = $this->guardIsRealImage($binary);

        $path = sprintf(
            '%s/%s/%s.%s',
            trim(config('readyroute.signatures.path'), '/'),
            trim($folder, '/'),
            Str::uuid(),
            $extension
        );

        Storage::disk($this->disk())->put($path, $binary);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function disk(): string
    {
        return config('readyroute.signatures.disk');
    }

    /**
     * A short-lived link to a stored signature.
     */
    public function temporaryUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk($this->disk())->temporaryUrl(
            $path,
            now()->addMinutes((int) config('readyroute.signatures.url_ttl_minutes', 15))
        );
    }

    public function store(Trip $trip, User $driver, string $payload, ?string $ip = null): TripSignature
    {
        $binary = $this->decode($payload);

        $this->guardSize($binary);
        $extension = $this->guardIsRealImage($binary);

        $disk = $this->disk();
        $path = sprintf(
            '%s/trip-%d/%s.%s',
            trim(config('readyroute.signatures.path'), '/'),
            $trip->id,
            Str::uuid(),
            $extension
        );

        return DB::transaction(function () use ($trip, $driver, $binary, $disk, $path, $ip) {
            $existing = TripSignature::where('trip_id', $trip->id)
                ->where('signer_role', 'driver')
                ->first();

            Storage::disk($disk)->put($path, $binary);

            $signature = TripSignature::updateOrCreate(
                ['trip_id' => $trip->id, 'signer_role' => 'driver'],
                [
                    'driver_id'      => $driver->id,
                    'signer_name'    => $driver->name,
                    'signature_path' => $path,
                    'signed_at'      => now(),
                    'ip_address'     => $ip,
                ]
            );

            // Re-signing replaces the image; the superseded file is removed
            // only once the new record is safely written.
            if ($existing && $existing->signature_path !== $path) {
                Storage::disk($disk)->delete($existing->signature_path);
            }

            return $signature;
        });
    }

    /**
     * Accepts either a bare base64 string or a data URI, since signature pads
     * differ on which they emit.
     */
    private function decode(string $payload): string
    {
        $payload = trim($payload);

        if (Str::startsWith($payload, 'data:')) {
            $comma = strpos($payload, ',');

            if ($comma === false) {
                throw new RuntimeException('The signature image could not be read.');
            }

            $payload = substr($payload, $comma + 1);
        }

        // Signature pads sometimes URL-encode the payload.
        $payload = str_replace([' ', "\n", "\r"], ['+', '', ''], $payload);

        $binary = base64_decode($payload, true);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('The signature image could not be read.');
        }

        return $binary;
    }

    private function guardSize(string $binary): void
    {
        $maxBytes = ((int) config('readyroute.signatures.max_kb', 512)) * 1024;

        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException(
                'That signature image is too large. Please try signing again.'
            );
        }
    }

    /**
     * Confirm the decoded bytes really are an image of an allowed type, rather
     * than trusting whatever the client claimed. Returns the file extension.
     */
    private function guardIsRealImage(string $binary): string
    {
        $info = @getimagesizefromstring($binary);

        if ($info === false || empty($info['mime'])) {
            throw new RuntimeException('The signature must be a PNG or JPEG image.');
        }

        if (! in_array($info['mime'], self::ALLOWED_MIME, true)) {
            throw new RuntimeException('The signature must be a PNG or JPEG image.');
        }

        // A pad that submits a blank canvas should not count as a signature.
        if ($info[0] < 10 || $info[1] < 10) {
            throw new RuntimeException('That signature looks empty. Please sign again.');
        }

        return $info['mime'] === 'image/jpeg' ? 'jpg' : 'png';
    }
}
