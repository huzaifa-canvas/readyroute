@extends('layouts/layoutMaster')

@section('title', 'Generate Invoice')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex align-items-center mb-4">
    <a href="{{ route('dispatcher.billing.index') }}" class="btn btn-icon btn-label-secondary me-3" aria-label="Back">
      <i class="ti tabler-arrow-left"></i>
    </a>
    <div>
      <h4 class="fw-bold text-heading mb-0">Generate Invoice</h4>
      <small class="text-muted">Will be created as {{ $number }}</small>
    </div>
  </div>

  @if($trips->isEmpty())
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="ti tabler-checks fs-1 d-block mb-2 text-success"></i>
        <h6>Nothing to bill</h6>
        <p class="mb-3 small">Every completed trip has already been invoiced.</p>
        <a href="{{ route('dispatcher.billing.index') }}" class="btn btn-label-primary btn-sm">Back to Billing</a>
      </div>
    </div>
  @else
  <form method="POST" action="{{ route('dispatcher.billing.store') }}" id="invoiceForm">
    @csrf

    <div class="row g-4">
      {{-- Payer --}}
      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Bill to</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="payer_name">Payer <span class="text-danger">*</span></label>
              <input type="text" id="payer_name" name="payer_name" required
                     class="form-control @error('payer_name') is-invalid @enderror"
                     placeholder="e.g. State Medicaid Portal" value="{{ old('payer_name') }}" />
              @error('payer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="payer_type">Payer type <span class="text-danger">*</span></label>
              <select id="payer_type" name="payer_type" class="form-select" required>
                @foreach(\App\Models\Invoice::PAYER_TYPES as $key => $label)
                  <option value="{{ $key }}" @selected(old('payer_type') === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label" for="payer_reference">Payer reference</label>
              <input type="text" id="payer_reference" name="payer_reference" class="form-control"
                     placeholder="Contract or provider number" value="{{ old('payer_reference') }}" />
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label" for="issued_on">Issued <span class="text-danger">*</span></label>
                <input type="date" id="issued_on" name="issued_on" required class="form-control"
                       value="{{ old('issued_on', today()->toDateString()) }}" />
              </div>
              <div class="col-6">
                <label class="form-label" for="due_on">Due</label>
                <input type="date" id="due_on" name="due_on"
                       class="form-control @error('due_on') is-invalid @enderror"
                       value="{{ old('due_on', today()->addDays(30)->toDateString()) }}" />
                @error('due_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="mb-0">
              <label class="form-label" for="notes">Notes</label>
              <textarea id="notes" name="notes" rows="2" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Trips --}}
      <div class="col-12 col-xl-8">
        <div class="card h-100">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h5 class="mb-0">Trips to bill</h5>
              <small class="text-muted">Tick the trips and set what each one is worth.</small>
            </div>
            <div class="text-end">
              <small class="text-muted d-block">Invoice total</small>
              <h5 class="mb-0 fw-bold" id="total" style="font-variant-numeric: tabular-nums;">$0.00</h5>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th style="width: 2.5rem;">
                    <input class="form-check-input" type="checkbox" id="checkAll" aria-label="Select all trips" />
                  </th>
                  <th>Trip</th>
                  <th class="d-none d-md-table-cell">Date</th>
                  <th class="d-none d-lg-table-cell text-end">Miles</th>
                  <th class="text-end" style="width: 9rem;">Amount</th>
                </tr>
              </thead>
              <tbody class="table-border-bottom-0">
                @foreach($trips as $i => $trip)
                @php($checked = $selected === [] || in_array($trip->id, $selected, true))
                <tr>
                  <td>
                    <input class="form-check-input trip-check" type="checkbox"
                           data-row="{{ $i }}" @checked($checked)
                           aria-label="Include {{ $trip->reference() }}" />
                  </td>
                  <td>
                    <span class="fw-semibold d-block">{{ $trip->reference() }}</span>
                    <small class="text-muted d-block text-truncate" style="max-width: 240px;">
                      {{ $trip->passengerName() }}
                    </small>
                    <small class="text-muted d-md-none">{{ optional($trip->pickup_date)->format('d M Y') }}</small>

                    <input type="hidden" name="items[{{ $i }}][trip_id]" value="{{ $trip->id }}"
                           data-field="trip_id" data-row="{{ $i }}" @disabled(! $checked) />
                    <input type="hidden" name="items[{{ $i }}][description]"
                           data-field="description" data-row="{{ $i }}" @disabled(! $checked)
                           value="{{ $trip->reference() }} — {{ $trip->passengerName() }} — {{ optional($trip->pickup_date)->format('d M Y') }}" />
                  </td>
                  <td class="d-none d-md-table-cell">{{ optional($trip->pickup_date)->format('d M Y') }}</td>
                  <td class="d-none d-lg-table-cell text-end" style="font-variant-numeric: tabular-nums;">
                    {{ $trip->actual_distance ? number_format((float) $trip->actual_distance, 1) : '—' }}
                  </td>
                  <td>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">$</span>
                      <input type="number" step="0.01" min="0" max="999999.99"
                             class="form-control amount text-end"
                             name="items[{{ $i }}][amount]" data-field="amount" data-row="{{ $i }}"
                             @disabled(! $checked) value="0.00"
                             aria-label="Amount for {{ $trip->reference() }}" />
                    </div>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="card-footer d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <small class="text-muted">
              The invoice is created as a draft; nothing is sent until you mark it submitted.
            </small>
            <button type="submit" class="btn btn-primary flex-shrink-0">
              <i class="ti tabler-file-invoice me-1"></i>Create Draft
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
  @endif
</div>
@endsection

@section('page-script')
<script>
  (function () {
    var checks = Array.from(document.querySelectorAll('.trip-check'));
    var total  = document.getElementById('total');

    // A row's inputs are disabled when unticked, so an unbilled trip never
    // reaches the server as a zero-amount line.
    function syncRow(check) {
      var row = check.dataset.row;
      document.querySelectorAll('[data-row="' + row + '"][data-field]').forEach(function (field) {
        field.disabled = !check.checked;
      });
    }

    function recalc() {
      var sum = 0;
      document.querySelectorAll('.amount').forEach(function (input) {
        if (!input.disabled) sum += parseFloat(input.value || 0) || 0;
      });
      total.textContent = '$' + sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    checks.forEach(function (check) {
      syncRow(check);
      check.addEventListener('change', function () { syncRow(check); recalc(); });
    });

    document.querySelectorAll('.amount').forEach(function (input) {
      input.addEventListener('input', recalc);
    });

    document.getElementById('checkAll')?.addEventListener('change', function (event) {
      checks.forEach(function (check) {
        check.checked = event.target.checked;
        syncRow(check);
      });
      recalc();
    });

    recalc();
  })();
</script>
@endsection
