<?php

namespace App\Http\Controllers\Api\Driver;

use App\Enums\InspectionItemStatus;
use App\Http\Resources\Driver\InspectionResource;
use App\Services\InspectionService;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class InspectionController extends BaseDriverController
{
    public function __construct(
        private readonly InspectionService $inspections,
        private readonly SignatureService $signatures,
    ) {
    }

    /**
     * Today's checklist. Creates the draft on first open, so the app only ever
     * needs this one call to render the screen.
     */
    public function today(): JsonResponse
    {
        try {
            $inspection = $this->inspections->todayFor($this->driver());
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->present($inspection));
    }

    /**
     * Record one item as passed, failed or back to unchecked.
     */
    public function answer(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => ['required', 'integer'],
            'status'  => ['required', 'string', Rule::in(InspectionItemStatus::values())],
            'note'    => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $inspection = $this->inspections->todayFor($this->driver());

            $this->inspections->answer(
                inspection: $inspection,
                itemId: $request->integer('item_id'),
                status: InspectionItemStatus::from($request->input('status')),
                note: $request->input('note'),
            );

            $inspection = $this->inspections->todayFor($this->driver());
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->present($inspection), 'Checklist updated.');
    }

    /**
     * Sign and lock the inspection for the day.
     */
    public function submit(Request $request): JsonResponse
    {
        $maxEncodedChars = ((int) config('readyroute.signatures.max_kb', 512)) * 1024 * 2;

        $request->validate([
            'signature' => ['required', 'string', 'max:' . $maxEncodedChars],
        ], [
            'signature.required' => 'A signature is required to submit this inspection.',
        ]);

        try {
            $inspection = $this->inspections->todayFor($this->driver());

            $inspection = $this->inspections->submit(
                $inspection,
                $request->input('signature')
            );
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(
            $this->present($inspection),
            $inspection->has_defects
                ? 'Inspection submitted. Your dispatcher has been alerted to the defects you reported.'
                : 'Inspection submitted. You are cleared to drive.'
        );
    }

    private function present($inspection): InspectionResource
    {
        return new InspectionResource(
            $inspection,
            $this->inspections->progress($inspection),
            $this->signatures->temporaryUrl($inspection->signature_path),
        );
    }
}
