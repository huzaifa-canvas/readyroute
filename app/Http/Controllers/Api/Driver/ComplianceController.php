<?php

namespace App\Http\Controllers\Api\Driver;

use App\Models\DriverDocument;
use Illuminate\Http\JsonResponse;

/**
 * What the driver has to keep current.
 *
 * Read-only: documents are uploaded and verified by the office, so the app
 * shows the driver what is expiring rather than letting them change it. The
 * summary is what the home screen badges off.
 */
class ComplianceController extends BaseDriverController
{
    public function index(): JsonResponse
    {
        $documents = DriverDocument::where('driver_id', $this->driver()->id)
            // Documents that never expire sort last; the rest by deadline.
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->get();

        $expired  = $documents->filter->isExpired();
        $expiring = $documents->filter->isExpiring();

        return $this->ok([
            'summary' => [
                'total'          => $documents->count(),
                'expired'        => $expired->count(),
                'expiring_soon'  => $expiring->count(),
                'is_clear'       => $expired->isEmpty() && $expiring->isEmpty(),
                // One line the app can put straight on the dashboard.
                'headline'       => $this->headline($expired->count(), $expiring->count(), $documents->count()),
                'warning_window_days' => DriverDocument::EXPIRY_WARNING_DAYS,
            ],

            'documents' => $documents->map(fn (DriverDocument $document) => [
                'id'         => $document->id,
                'type'       => $document->type,
                'label'      => $document->typeLabel(),
                'reference'  => $document->reference,
                'issued_on'  => optional($document->issued_on)->toDateString(),
                'expires_on' => optional($document->expires_on)->toDateString(),

                'status'       => $document->deriveStatus(),
                'status_label' => $document->statusLabel(),

                // Negative once it has passed, null when it never expires.
                'days_until_expiry' => $document->daysUntilExpiry(),

                'notes' => $document->notes,
            ])->values(),
        ]);
    }

    private function headline(int $expired, int $expiring, int $total): string
    {
        if ($total === 0) {
            return 'No documents on file. Contact your dispatcher.';
        }

        if ($expired > 0) {
            return $expired === 1
                ? '1 document has expired.'
                : $expired . ' documents have expired.';
        }

        if ($expiring > 0) {
            return $expiring === 1
                ? '1 document expires soon.'
                : $expiring . ' documents expire soon.';
        }

        return 'All documents are up to date.';
    }
}
