@extends('layouts/layoutMaster')

@section('title', $invoice->number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('dispatcher.billing.index') }}">Billing &amp; Claims</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $invoice->number }}</li>
    </ol>
  </nav>

  @if($invoice->status === 'rejected')
    <div class="alert alert-danger" role="alert">
      <i class="ti tabler-file-x me-2"></i>
      <strong>Rejected.</strong>
      {{ $invoice->rejection_reason ?: 'No reason was recorded.' }}
      Fix the issue and mark it submitted again.
    </div>
  @elseif($invoice->isOverdue())
    <div class="alert alert-warning" role="alert">
      <i class="ti tabler-clock-exclamation me-2"></i>
      Past due since {{ $invoice->due_on->format('d M Y') }}.
    </div>
  @endif

  <div class="row g-4">

    {{-- The invoice --}}
    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
          <div>
            <h5 class="mb-1">{{ $invoice->number }}</h5>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge {{ $invoice->statusClass() }}">{{ $invoice->statusLabel() }}</span>
              <span class="badge bg-label-secondary">{{ $invoice->payerTypeLabel() }}</span>
            </div>
          </div>
          <div class="text-end">
            <small class="text-muted d-block">Total</small>
            <h4 class="fw-bold mb-0" style="font-variant-numeric: tabular-nums;">
              ${{ number_format((float) $invoice->amount, 2) }}
            </h4>
          </div>
        </div>

        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Payer</dt>
            <dd class="col-7 col-sm-8 py-1 text-break">
              {{ $invoice->payer_name }}
              @if($invoice->payer_reference)
                <small class="text-muted d-block">Ref: {{ $invoice->payer_reference }}</small>
              @endif
            </dd>

            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Issued</dt>
            <dd class="col-7 col-sm-8 py-1">{{ $invoice->issued_on->format('d M Y') }}</dd>

            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Due</dt>
            <dd class="col-7 col-sm-8 py-1">
              {{ $invoice->due_on ? $invoice->due_on->format('d M Y') : '—' }}
            </dd>

            @if($invoice->submitted_at)
              <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Submitted</dt>
              <dd class="col-7 col-sm-8 py-1">{{ $invoice->submitted_at->format('d M Y, g:i A') }}</dd>
            @endif

            @if($invoice->paid_at)
              <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Paid</dt>
              <dd class="col-7 col-sm-8 py-1">{{ $invoice->paid_at->format('d M Y, g:i A') }}</dd>
            @endif

            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Created by</dt>
            <dd class="col-7 col-sm-8 py-1">
              {{ $invoice->creator?->name ?? '—' }}
              <small class="text-muted">{{ $invoice->created_at->diffForHumans() }}</small>
            </dd>
          </dl>

          @if($invoice->notes)
            <hr />
            <h6 class="mb-2">Notes</h6>
            <p class="text-muted small mb-0 text-break">{{ $invoice->notes }}</p>
          @endif
        </div>

        <div class="table-responsive border-top">
          <table class="table mb-0 align-middle">
            <thead>
              <tr>
                <th>Line</th>
                <th class="d-none d-md-table-cell">Trip</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @foreach($invoice->items as $item)
              <tr>
                <td>
                  <span class="d-block text-break">{{ $item->description }}</span>
                  @if($item->trip)
                    <small class="text-muted d-md-none">{{ $item->trip->reference() }}</small>
                  @endif
                </td>
                <td class="d-none d-md-table-cell">
                  @if($item->trip)
                    <a href="{{ route('dispatcher.trip.details', $item->trip->id) }}">{{ $item->trip->reference() }}</a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-end" style="font-variant-numeric: tabular-nums;">
                  ${{ number_format((float) $item->amount, 2) }}
                </td>
              </tr>
              @endforeach
            </tbody>
            <tfoot class="border-top">
              <tr>
                <th colspan="2" class="text-end d-none d-md-table-cell">Total</th>
                <th class="text-end d-md-none">Total</th>
                <th class="text-end" style="font-variant-numeric: tabular-nums;">
                  ${{ number_format((float) $invoice->amount, 2) }}
                </th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    {{-- Actions --}}
    <div class="col-12 col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">Status</h6></div>
        <div class="card-body d-flex flex-column gap-3">

          @php($next = match ($invoice->status) {
                'draft'      => ['submitted', 'Mark Submitted', 'primary', 'tabler-send'],
                'submitted'  => ['processing', 'Mark Processing', 'info', 'tabler-progress'],
                'processing' => ['paid', 'Mark Paid', 'success', 'tabler-cash'],
                'rejected'   => ['submitted', 'Resubmit', 'primary', 'tabler-refresh'],
                default      => null,
              })

          @if($next)
            <form method="POST" action="{{ route('dispatcher.billing.status', $invoice->id) }}">
              @csrf
              <input type="hidden" name="status" value="{{ $next[0] }}" />
              <button type="submit" class="btn btn-{{ $next[2] }} w-100">
                <i class="ti {{ $next[3] }} me-1"></i>{{ $next[1] }}
              </button>
            </form>
          @else
            <div class="text-center py-2">
              <i class="ti tabler-circle-check fs-2 text-success d-block mb-1"></i>
              <small class="text-muted">Paid {{ $invoice->paid_at?->diffForHumans() }}</small>
            </div>
          @endif

          @if($invoice->status !== 'paid' && $invoice->status !== 'rejected')
            <button type="button" class="btn btn-label-danger w-100"
                    data-bs-toggle="modal" data-bs-target="#rejectModal">
              <i class="ti tabler-file-x me-1"></i>Mark Rejected
            </button>
          @endif

          <a href="{{ route('dispatcher.billing.print', $invoice->id) }}" target="_blank" rel="noopener"
             class="btn btn-label-secondary w-100">
            <i class="ti tabler-printer me-1"></i>Print / Save PDF
          </a>

          @if($invoice->status === 'draft')
            <form method="POST" action="{{ route('dispatcher.billing.delete', $invoice->id) }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-text-danger w-100"
                      onclick="return confirm('Delete draft {{ $invoice->number }}?')">
                <i class="ti tabler-trash me-1"></i>Delete draft
              </button>
            </form>
          @endif
        </div>
      </div>

      @if($invoice->isEditable())
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Edit details</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('dispatcher.billing.update', $invoice->id) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
              <label class="form-label" for="payer_name">Payer</label>
              <input type="text" id="payer_name" name="payer_name" class="form-control" required
                     value="{{ old('payer_name', $invoice->payer_name) }}" />
            </div>
            <div class="mb-3">
              <label class="form-label" for="payer_type">Payer type</label>
              <select id="payer_type" name="payer_type" class="form-select" required>
                @foreach(\App\Models\Invoice::PAYER_TYPES as $key => $label)
                  <option value="{{ $key }}" @selected($invoice->payer_type === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="payer_reference">Payer reference</label>
              <input type="text" id="payer_reference" name="payer_reference" class="form-control"
                     value="{{ old('payer_reference', $invoice->payer_reference) }}" />
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label" for="issued_on">Issued</label>
                <input type="date" id="issued_on" name="issued_on" class="form-control" required
                       value="{{ old('issued_on', $invoice->issued_on->toDateString()) }}" />
              </div>
              <div class="col-6">
                <label class="form-label" for="due_on">Due</label>
                <input type="date" id="due_on" name="due_on" class="form-control"
                       value="{{ old('due_on', optional($invoice->due_on)->toDateString()) }}" />
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="notes">Notes</label>
              <textarea id="notes" name="notes" rows="2" class="form-control"
                        maxlength="2000">{{ old('notes', $invoice->notes) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Save</button>
          </form>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>

{{-- Rejection --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" action="{{ route('dispatcher.billing.status', $invoice->id) }}">
      @csrf
      <input type="hidden" name="status" value="rejected" />
      <div class="modal-header">
        <h5 class="modal-title">Mark {{ $invoice->number }} rejected</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="rejection_reason">Reason</label>
        <input type="text" id="rejection_reason" name="rejection_reason" class="form-control"
               maxlength="255" placeholder="e.g. Missing signature on trip #1041" />
        <small class="text-muted">Shown on the list so the problem is visible without opening the invoice.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Mark Rejected</button>
      </div>
    </form>
  </div>
</div>
@endsection
