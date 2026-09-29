@extends('layouts/layoutMaster')

@section('title', 'Billing & Claims')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php
    $tiles = [
      ['Unbilled trips',  number_format($summary['unbilled_trips']),                  'tabler-file-invoice',  'primary', route('dispatcher.billing.unbilled')],
      ['Outstanding AR',  '$' . number_format($summary['outstanding'], 2),            'tabler-cash',          'warning', route('dispatcher.billing.index', ['status' => 'submitted'])],
      ['Rejected',        number_format($summary['rejected']),                        'tabler-file-x',        'danger',  route('dispatcher.billing.index', ['status' => 'rejected'])],
      ['Paid this month', '$' . number_format($summary['paid_this_month'], 2),        'tabler-circle-check',  'success', route('dispatcher.billing.index', ['status' => 'paid'])],
    ];
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Billing &amp; Claims</h4>
      <p class="text-muted mb-0 small">Invoices to clients and claims to brokers and payers.</p>
    </div>
    <a href="{{ route('dispatcher.billing.create') }}" class="btn btn-primary">
      <i class="ti tabler-plus me-1"></i> Generate Invoice
    </a>
  </div>

  @if($summary['overdue'] > 0)
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
      <i class="ti tabler-clock-exclamation flex-shrink-0"></i>
      <span>
        {{ $summary['overdue'] }} {{ $summary['overdue'] === 1 ? 'invoice is' : 'invoices are' }} past their due date.
      </span>
    </div>
  @endif

  <div class="row g-4 mb-4">
    @foreach($tiles as [$label, $value, $icon, $colour, $href])
    <div class="col-6 col-xl-3">
      <a href="{{ $href }}" class="card h-100 text-decoration-none">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-{{ $colour }}"><i class="ti {{ $icon }}"></i></span>
          </div>
          <div class="min-w-0">
            <h5 class="fw-bold mb-0 text-truncate" style="font-variant-numeric: tabular-nums;">{{ $value }}</h5>
            <small class="text-muted">{{ $label }}</small>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('dispatcher.billing.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
          <label class="form-label" for="payer">Payer</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ti tabler-search"></i></span>
            <input type="text" id="payer" name="payer" class="form-control"
                   placeholder="Broker, insurer or client" value="{{ request('payer') }}" />
          </div>
        </div>
        <div class="col-8 col-md-4">
          <label class="form-label" for="status">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">All statuses</option>
            @foreach(\App\Models\Invoice::STATUSES as $key => $label)
              <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-4 col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-label-primary flex-grow-1">Filter</button>
          @if(request()->hasAny(['payer', 'status']))
            <a href="{{ route('dispatcher.billing.index') }}" class="btn btn-label-secondary">Clear</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Invoices &amp; Claims</h5>
      <span class="badge bg-label-secondary">{{ $invoices->total() }}</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Invoice</th>
            <th class="d-none d-md-table-cell">Payer</th>
            <th class="d-none d-lg-table-cell">Issued</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($invoices as $invoice)
          <tr>
            <td>
              <a href="{{ route('dispatcher.billing.show', $invoice->id) }}"
                 class="fw-semibold text-heading d-block">{{ $invoice->number }}</a>
              <small class="text-muted d-block">
                {{ $invoice->items_count }} {{ $invoice->items_count === 1 ? 'line' : 'lines' }}
              </small>
              <small class="text-muted d-md-none d-block text-truncate">{{ $invoice->payer_name }}</small>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="text-body d-block text-truncate" style="max-width: 220px;">{{ $invoice->payer_name }}</span>
              <small class="text-muted">{{ $invoice->payerTypeLabel() }}</small>
            </td>
            <td class="d-none d-lg-table-cell">
              <span class="text-body">{{ $invoice->issued_on->format('d M Y') }}</span>
              @if($invoice->due_on)
                <small class="d-block {{ $invoice->isOverdue() ? 'text-danger' : 'text-muted' }}">
                  Due {{ $invoice->due_on->format('d M Y') }}
                </small>
              @endif
            </td>
            <td class="text-end fw-semibold" style="font-variant-numeric: tabular-nums;">
              ${{ number_format((float) $invoice->amount, 2) }}
            </td>
            <td>
              <span class="badge {{ $invoice->statusClass() }}">{{ $invoice->statusLabel() }}</span>
              @if($invoice->status === 'rejected' && $invoice->rejection_reason)
                <small class="d-block text-muted text-truncate" style="max-width: 160px;"
                       title="{{ $invoice->rejection_reason }}">{{ $invoice->rejection_reason }}</small>
              @endif
            </td>
            <td class="text-end">
              <div class="d-flex gap-2 justify-content-end">
                <a href="{{ route('dispatcher.billing.show', $invoice->id) }}"
                   class="btn btn-sm btn-label-primary" aria-label="Open {{ $invoice->number }}">
                  <i class="ti tabler-eye"></i>
                </a>
                <a href="{{ route('dispatcher.billing.print', $invoice->id) }}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-label-secondary" aria-label="Print {{ $invoice->number }}">
                  <i class="ti tabler-printer"></i>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="ti tabler-file-invoice fs-1 d-block mb-2 text-secondary"></i>
              <h6>No invoices yet</h6>
              <p class="mb-3 small">
                {{ $summary['unbilled_trips'] > 0
                    ? $summary['unbilled_trips'] . ' completed trip(s) are waiting to be billed.'
                    : 'Completed trips appear here once there are some to bill.' }}
              </p>
              <a href="{{ route('dispatcher.billing.create') }}" class="btn btn-primary btn-sm">Generate Invoice</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($invoices->hasPages())
    <div class="card-footer d-flex justify-content-center py-3 border-top">
      {{ $invoices->links() }}
    </div>
    @endif
  </div>
</div>
@endsection
