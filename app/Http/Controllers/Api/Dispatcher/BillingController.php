<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Resources\Dispatcher\TripListResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Invoices the company raises against payers.
 *
 * Not to be confused with the company's own subscription, which is money going
 * the other way and lives in SubscriptionController.
 */
class BillingController extends BaseDispatcherController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId();

        $query = Invoice::forDispatcher($companyId)->withCount('items');

        if ($request->filled('status') && array_key_exists($request->input('status'), Invoice::STATUSES)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payer')) {
            $query->where('payer_name', 'like', '%' . $request->input('payer') . '%');
        }

        $page = $query->latest('issued_on')->latest('id')->paginate($this->perPage());

        return response()->json([
            'status'  => true,
            'message' => null,
            'data'    => [
                'invoices' => collect($page->items())->map(fn ($i) => $this->row($i))->values(),
                'summary'  => [
                    'unbilled_trips' => $this->unbilledTrips()->count(),
                    'outstanding'    => (float) Invoice::forDispatcher($companyId)->outstanding()->sum('amount'),
                    'rejected'       => Invoice::forDispatcher($companyId)->rejected()->count(),
                    'paid_this_month' => (float) Invoice::forDispatcher($companyId)
                        ->where('status', 'paid')
                        ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->sum('amount'),
                    'overdue' => Invoice::forDispatcher($companyId)
                        ->outstanding()
                        ->whereNotNull('due_on')
                        ->whereDate('due_on', '<', today())
                        ->count(),
                ],
                'statuses'    => collect(Invoice::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'payer_types' => collect(Invoice::PAYER_TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'has_more'     => $page->hasMorePages(),
            ],
            'errors' => null,
        ]);
    }

    /**
     * Completed trips with no invoice line against them — the queue this
     * screen exists to drain.
     */
    public function unbilled(): JsonResponse
    {
        return $this->ok(
            TripListResource::collection(
                $this->unbilledTrips()
                    ->with(['client', 'driver', 'vehicle'])
                    ->orderBy('pickup_date')
                    ->limit(200)
                    ->get()
            )
        );
    }

    public function show(int $id): JsonResponse
    {
        $invoice = $this->find($id);

        if (! $invoice) {
            return $this->notFound('Invoice not found.');
        }

        $invoice->load(['items.trip.client', 'creator']);

        return $this->ok($this->row($invoice) + [
            'notes' => $invoice->notes,
            'items' => $invoice->items->map(fn (InvoiceItem $item) => [
                'id'          => $item->id,
                'description' => $item->description,
                'amount'      => (float) $item->amount,
                'trip' => $item->trip ? [
                    'id'        => $item->trip->id,
                    'reference' => $item->trip->reference(),
                    'passenger' => $item->trip->passengerName(),
                    'date'      => optional($item->trip->pickup_date)->toDateString(),
                ] : null,
            ])->values(),
            'created_by' => $invoice->creator?->name,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $companyId = $this->companyId();

        $invoice = DB::transaction(function () use ($data, $companyId) {
            $invoice = Invoice::create([
                'dispatcher_id'   => $companyId,
                'number'          => Invoice::nextNumber($companyId),
                'payer_name'      => $data['payer_name'],
                'payer_type'      => $data['payer_type'],
                'payer_reference' => $data['payer_reference'] ?? null,
                'issued_on'       => $data['issued_on'],
                'due_on'          => $data['due_on'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => 'draft',
                'created_by'      => $this->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                // A trip id is only accepted if it belongs to this company and
                // has not already been billed.
                $tripId = null;

                if (! empty($item['trip_id'])) {
                    $tripId = $this->unbilledTrips()->whereKey((int) $item['trip_id'])->value('id');
                }

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'trip_id'     => $tripId,
                    'description' => $item['description'],
                    'amount'      => $item['amount'],
                ]);
            }

            $invoice->refreshTotal();

            return $invoice;
        });

        return $this->created(
            $this->row($invoice->fresh()->loadCount('items')),
            'Invoice ' . $invoice->number . ' created as a draft.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = $this->find($id);

        if (! $invoice) {
            return $this->notFound('Invoice not found.');
        }

        if (! $invoice->isEditable()) {
            return $this->fail('A paid invoice cannot be changed.', 422);
        }

        $data = $request->validate($this->rules());

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'payer_name'      => $data['payer_name'],
                'payer_type'      => $data['payer_type'],
                'payer_reference' => $data['payer_reference'] ?? null,
                'issued_on'       => $data['issued_on'],
                'due_on'          => $data['due_on'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            /*
             * The trips already on this invoice count as allowed.
             *
             * Taken before the lines are cleared: afterwards those trips look
             * unbilled to everyone, and a trip the biller simply kept would be
             * rejected for not being on a list it had just been removed from.
             */
            $allowed = $this->unbilledTrips()->pluck('id')
                ->merge($invoice->items()->pluck('trip_id')->filter())
                ->unique();

            $invoice->items()->delete();

            foreach ($data['items'] as $item) {
                $tripId = ! empty($item['trip_id']) && $allowed->contains((int) $item['trip_id'])
                    ? (int) $item['trip_id']
                    : null;

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'trip_id'     => $tripId,
                    'description' => $item['description'],
                    'amount'      => $item['amount'],
                ]);
            }

            $invoice->refreshTotal();
        });

        return $this->ok($this->row($invoice->fresh()->loadCount('items')), 'Invoice updated.');
    }

    /**
     * Move an invoice along its lifecycle. Each step records its own
     * timestamp, so "when was this submitted" never has to be inferred.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $invoice = $this->find($id);

        if (! $invoice) {
            return $this->notFound('Invoice not found.');
        }

        $data = $request->validate([
            'status'           => ['required', Rule::in(array_keys(Invoice::STATUSES))],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $changes = ['status' => $data['status']];

        $changes += match ($data['status']) {
            'submitted'  => ['submitted_at' => $invoice->submitted_at ?: now(), 'rejection_reason' => null],
            'processing' => ['submitted_at' => $invoice->submitted_at ?: now(), 'rejection_reason' => null],
            'paid'       => ['paid_at' => now(), 'rejection_reason' => null],
            'rejected'   => ['rejection_reason' => $data['rejection_reason'] ?? null, 'paid_at' => null],
            default      => ['submitted_at' => null, 'paid_at' => null, 'rejection_reason' => null],
        };

        $invoice->forceFill($changes)->save();

        return $this->ok(
            $this->row($invoice->fresh()->loadCount('items')),
            'Invoice marked as ' . $invoice->statusLabel() . '.'
        );
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'payer_name'      => ['required', 'string', 'max:160'],
            'payer_type'      => ['required', Rule::in(array_keys(Invoice::PAYER_TYPES))],
            'payer_reference' => ['nullable', 'string', 'max:120'],
            'issued_on'       => ['required', 'date'],
            'due_on'          => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes'           => ['nullable', 'string', 'max:2000'],

            'items'               => ['required', 'array', 'min:1'],
            'items.*.trip_id'     => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount'      => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Invoice $invoice): array
    {
        return [
            'id'     => $invoice->id,
            'number' => $invoice->number,
            'payer'  => [
                'name'      => $invoice->payer_name,
                'type'      => $invoice->payer_type,
                'type_label' => Invoice::PAYER_TYPES[$invoice->payer_type] ?? $invoice->payer_type,
                'reference' => $invoice->payer_reference,
            ],
            'amount'       => (float) $invoice->amount,
            'status'       => $invoice->status,
            'status_label' => $invoice->statusLabel(),
            'is_editable'  => $invoice->isEditable(),
            'issued_on'    => optional($invoice->issued_on)->toDateString(),
            'due_on'       => optional($invoice->due_on)->toDateString(),
            'is_overdue'   => $invoice->due_on
                && $invoice->due_on->isPast()
                && ! in_array($invoice->status, ['paid'], true),
            'submitted_at' => optional($invoice->submitted_at)->toIso8601String(),
            'paid_at'      => optional($invoice->paid_at)->toIso8601String(),
            'rejection_reason' => $invoice->rejection_reason,
            'item_count'   => $invoice->items_count ?? $invoice->items()->count(),
            'created_at'   => optional($invoice->created_at)->toIso8601String(),
        ];
    }

    /**
     * Completed trips with no invoice line against them.
     */
    private function unbilledTrips()
    {
        return Trip::where('dispatcher_id', $this->companyId())
            ->where('status', TripStatus::Completed->value)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('invoice_items')
                    ->whereColumn('invoice_items.trip_id', 'trips.id');
            });
    }

    private function find(int $id): ?Invoice
    {
        return Invoice::forDispatcher($this->companyId())->find($id);
    }
}
