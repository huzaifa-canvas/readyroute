@extends('layouts/layoutMaster')

@section('title', 'Unbilled Trips')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('dispatcher.billing.index') }}">Billing &amp; Claims</a></li>
      <li class="breadcrumb-item active" aria-current="page">Unbilled trips</li>
    </ol>
  </nav>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Unbilled Trips</h4>
      <p class="text-muted mb-0 small">Completed trips with no invoice line against them.</p>
    </div>
  </div>

  <form method="GET" action="{{ route('dispatcher.billing.create') }}">
    <div class="card">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">{{ $trips->total() }} ready to bill</h5>
        <button type="submit" class="btn btn-primary" @disabled($trips->isEmpty())>
          <i class="ti tabler-file-invoice me-1"></i>Invoice selected
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th style="width: 2.5rem;">
                <input class="form-check-input" type="checkbox" id="checkAll" aria-label="Select all" />
              </th>
              <th>Trip</th>
              <th class="d-none d-md-table-cell">Completed</th>
              <th class="d-none d-lg-table-cell">Driver</th>
              <th class="text-end d-none d-sm-table-cell">Miles</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @forelse($trips as $trip)
            <tr>
              <td>
                <input class="form-check-input trip-check" type="checkbox" name="trips[]" value="{{ $trip->id }}"
                       aria-label="Select {{ $trip->reference() }}" />
              </td>
              <td>
                <a href="{{ route('dispatcher.trip.details', $trip->id) }}" class="fw-semibold d-block">
                  {{ $trip->reference() }}
                </a>
                <small class="text-muted d-block text-truncate" style="max-width: 240px;">
                  {{ $trip->passengerName() }}
                </small>
                <small class="text-muted d-md-none">
                  {{ optional($trip->completed_at)->format('d M Y') }}
                </small>
              </td>
              <td class="d-none d-md-table-cell">
                {{ optional($trip->completed_at)->format('d M Y') ?: optional($trip->pickup_date)->format('d M Y') }}
              </td>
              <td class="d-none d-lg-table-cell">{{ $trip->driver?->name ?? '—' }}</td>
              <td class="text-end d-none d-sm-table-cell" style="font-variant-numeric: tabular-nums;">
                {{ $trip->actual_distance ? number_format((float) $trip->actual_distance, 1) : '—' }}
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="text-center py-5 text-muted">
                <i class="ti tabler-checks fs-1 d-block mb-2 text-success"></i>
                <h6>Everything is billed</h6>
                <p class="mb-0 small">No completed trip is waiting for an invoice.</p>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($trips->hasPages())
      <div class="card-footer d-flex justify-content-center py-3 border-top">
        {{ $trips->links() }}
      </div>
      @endif
    </div>
  </form>
</div>
@endsection

@section('page-script')
<script>
  document.getElementById('checkAll')?.addEventListener('change', function (event) {
    document.querySelectorAll('.trip-check').forEach(function (check) {
      check.checked = event.target.checked;
    });
  });
</script>
@endsection
