<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Billing & Claims.
 *
 * A claim and an invoice are one record here, distinguished by who is being
 * billed: a broker or a state programme makes it a claim, a client makes it an
 * invoice. The board leads with the three numbers a biller checks first —
 * what is unbilled, what is owed, and what has been rejected.
 */
class BillingController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $query = Invoice::forDispatcher($companyId)->withCount('items');

        if ($request->filled('status') && array_key_exists($request->status, Invoice::STATUSES)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payer')) {
            $query->where('payer_name', 'like', '%' . $request->input('payer') . '%');
        }

        $invoices = $query->latest('issued_on')->latest('id')->paginate(15)->withQueryString();

        $summary = [
            'unbilled_trips' => $this->unbilledTrips($companyId)->count(),
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
        ];

        return view('content.dispatcher.billing.index', compact('invoices', 'summary'));
    }

    /**
     * The trips that have been delivered but never billed — the queue this
     * screen exists to drain.
     */
    public function unbilled()
    {
        $companyId = auth()->user()->companyId();

        $trips = $this->unbilledTrips($companyId)
            ->with(['client', 'driver'])
            ->orderBy('pickup_date')
            ->paginate(50);

        return view('content.dispatcher.billing.unbilled', compact('trips'));
    }

    public function create(Request $request)
    {
        $companyId = auth()->user()->companyId();

        // Pre-select whatever the biller ticked on the unbilled list.
        $selected = collect($request->input('trips', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        $trips = $this->unbilledTrips($companyId)
            ->with(['client', 'driver'])
            ->when($selected !== [], fn ($q) => $q->whereIn('id', $selected))
            ->orderBy('pickup_date')
            ->get();

        $number = Invoice::nextNumber($companyId);

        return view('content.dispatcher.billing.create', compact('trips', 'number', 'selected'));
    }

    public function store(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $data = $request->validate([
            'payer_name'      => ['required', 'string', 'max:160'],
            'payer_type'      => ['required', Rule::in(array_keys(Invoice::PAYER_TYPES))],
            'payer_reference' => ['nullable', 'string', 'max:120'],
            'issued_on'       => ['required', 'date'],
            'due_on'          => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes'           => ['nullable', 'string', 'max:2000'],

            'items'              => ['required', 'array', 'min:1'],
            'items.*.trip_id'    => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount'     => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

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
                'created_by'      => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                // A trip id is only accepted if it belongs to this company and
                // has not already been billed.
                $tripId = null;

                if (! empty($item['trip_id'])) {
                    $tripId = $this->unbilledTrips($companyId)
                        ->whereKey((int) $item['trip_id'])
                        ->value('id');
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

        return redirect()
            ->route('dispatcher.billing.show', $invoice->id)
            ->with('success', 'Invoice ' . $invoice->number . ' created as a draft.');
    }

    public function show($id)
    {
        $invoice = $this->find($id);
        $invoice->load(['items.trip.client', 'creator', 'dispatcher']);

        return view('content.dispatcher.billing.show', compact('invoice'));
    }

    /**
     * Move an invoice along its lifecycle. Each step records its own
     * timestamp, so "when was this submitted" never has to be inferred.
     */
    public function updateStatus(Request $request, $id)
    {
        $invoice = $this->find($id);

        $data = $request->validate([
            'status'           => ['required', Rule::in(array_keys(Invoice::STATUSES))],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $changes = ['status' => $data['status']];

        $changes += match ($data['status']) {
            'submitted'  => ['submitted_at' => $invoice->submitted_at ?: now(), 'rejection_reason' => null],
            'processing' => ['submitted_at' => $invoice->submitted_at ?: now(), 'rejection_reason' => null],
            'paid'       => ['paid_at' => now(), 'rejection_reason' => null],
            'rejected'   => ['rejection_reason' => $data['rejection_reason'], 'paid_at' => null],
            default      => ['submitted_at' => null, 'paid_at' => null, 'rejection_reason' => null],
        };

        $invoice->forceFill($changes)->save();

        return back()->with('success', 'Invoice marked as ' . $invoice->statusLabel() . '.');
    }

    public function update(Request $request, $id)
    {
        $invoice = $this->find($id);

        if (! $invoice->isEditable()) {
            return back()->with('error', 'A paid invoice cannot be changed.');
        }

        $data = $request->validate([
            'payer_name'      => ['required', 'string', 'max:160'],
            'payer_type'      => ['required', Rule::in(array_keys(Invoice::PAYER_TYPES))],
            'payer_reference' => ['nullable', 'string', 'max:120'],
            'issued_on'       => ['required', 'date'],
            'due_on'          => ['nullable', 'date', 'after_or_equal:issued_on'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice->update($data);

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy($id)
    {
        $invoice = $this->find($id);

        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only a draft can be deleted. Mark it rejected instead to keep the record.');
        }

        $number = $invoice->number;
        $invoice->delete();

        return redirect()
            ->route('dispatcher.billing.index')
            ->with('success', 'Draft ' . $number . ' deleted.');
    }

    /**
     * A printable copy. Uses the browser's own print dialog rather than a PDF
     * library, which keeps the layout in one place.
     */
    public function print($id)
    {
        $invoice = $this->find($id);
        $invoice->load(['items.trip', 'dispatcher']);

        return view('content.dispatcher.billing.print', compact('invoice'));
    }

    /**
     * Completed trips with no invoice line against them.
     */
    private function unbilledTrips(int $companyId)
    {
        return Trip::where('dispatcher_id', $companyId)
            ->where('status', TripStatus::Completed->value)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('invoice_items')
                    ->whereColumn('invoice_items.trip_id', 'trips.id');
            });
    }

    private function find($id): Invoice
    {
        return Invoice::forDispatcher(auth()->user()->companyId())->findOrFail($id);
    }
}
