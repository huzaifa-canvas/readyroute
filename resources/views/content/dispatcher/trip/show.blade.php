@extends('layouts/layoutMaster')

@section('title', 'Trip #' . $trip->id . ' Details')

@section('page-style')
<style>
  /*
    The template's danger alert is its brand red on a tint of the same red,
    which measures 2.69:1 — fine for a passing notice, too light for the one
    place on this page that says a vehicle was signed off with a fault. The
    ground stays as the template drew it; only the text is taken darker, and
    it is derived from the accent so a re-themed palette carries through.

    Scoped to this alert on purpose: every other alert in the panel is left
    exactly as the template has it.
  */
  .inspection-defects {
    --bs-alert-color: color-mix(in srgb, var(--bs-danger) 68%, #000);
  }

  .inspection-defects .alert-heading {
    color: color-mix(in srgb, var(--bs-danger) 68%, #000) !important;
  }
</style>
@endsection

@section('content')

{{-- Header Action Bar --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="{{ route('dispatcher.trip.list') }}" class="btn btn-icon btn-label-secondary me-2">
      <i class="ti tabler-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Trip #{{ $trip->id }}</h4>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('dispatcher.trip.edit', $trip->id) }}" class="btn btn-outline-primary px-4">
      <i class="ti tabler-edit me-1"></i> Edit Trip
    </a>
    {{-- Cancelling keeps the trip on the record and tells the driver. Once a
         trip is completed or already cancelled there is nothing left to do. --}}
    @if(! $trip->statusEnum()?->isTerminal() && auth()->user()->hasPermission('trips.cancel'))
      <form action="{{ route('dispatcher.trip.cancel', $trip->id) }}" method="POST" onsubmit="return confirm('Cancel this trip? It stays on the record and the assigned driver is notified.');">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-warning px-4">
          <i class="ti tabler-ban me-1"></i> Cancel Trip
        </button>
      </form>
    @endif

    {{-- Deleting removes the trip from the panel. Kept as the quieter of the
         two actions, because cancelling is almost always the right one. --}}
    @if(auth()->user()->hasPermission('trips.delete'))
      <form action="{{ route('dispatcher.trip.delete', $trip->id) }}" method="POST" onsubmit="return confirm('Delete this trip? It is removed from the panel completely. To record that a booked trip did not happen, cancel it instead.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger px-4">
          <i class="ti tabler-trash me-1"></i> Delete
        </button>
      </form>
    @endif
  </div>
</div>

{{-- Status Banner --}}
@php
  $tripStatus = $trip->statusEnum();
  $statusText = $tripStatus?->label() ?? 'Scheduled';
  $statusBg = match ($tripStatus?->value) {
    'en_route'        => 'bg-label-info text-info border-info',
    'arrived_pickup',
    'arrived_dropoff' => 'bg-label-primary text-primary border-primary',
    'in_progress'     => 'bg-label-success text-success border-success',
    'completed'       => 'bg-label-primary text-primary border-primary',
    'cancelled'       => 'bg-label-danger text-danger border-danger',
    default           => 'bg-label-warning text-warning border-warning',
  };
@endphp

<div class="card mb-4 border shadow-none {{ $statusBg }} p-3">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <small class="d-block opacity-75 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Current Status</small>
      <h5 class="fw-bold mb-0 text-inherit">{{ $statusText }}</h5>
      @if($trip->cancelled_at)
        <small class="opacity-75">Cancelled {{ $trip->cancelled_at->format('M d, Y \a\t h:i A') }}</small>
      @endif
    </div>
    <div class="text-end">
      <small class="opacity-75"><i class="ti tabler-clock me-1"></i>Pickup: {{ \Carbon\Carbon::parse($trip->pickup_date)->format('M d, Y') }} at {{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</small>
    </div>
  </div>
</div>

{{-- Passenger tracking link.
     Minted when the driver sets off. Until an SMS provider is connected the
     dispatcher passes it on by hand, which is why it is copyable here. --}}
@php($trackingUrl = $trip->public_token ? route('track.show', $trip->public_token) : null)

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
      <div class="flex-grow-1 min-w-0">
        <h6 class="mb-1">
          <i class="ti tabler-map-pin-share me-1 text-primary"></i>Passenger tracking link
        </h6>
        @if($trackingUrl)
          <p class="text-muted small mb-0">
            Send this to {{ $trip->passengerName() }}. No app or login needed — it opens in any phone browser
            and shows the vehicle's position, the driver and an ETA.
          </p>
        @else
          <p class="text-muted small mb-0">
            The link is created automatically when the driver marks themselves en route.
            You can also create it now to send in advance.
          </p>
        @endif
      </div>

      <div class="flex-shrink-0" style="min-width: 0;">
        @if($trackingUrl)
          <div class="input-group">
            <input type="text" class="form-control" id="trackingLink" readonly
                   value="{{ $trackingUrl }}" aria-label="Passenger tracking link"
                   style="min-width: 0;" />
            <button class="btn btn-primary" type="button" id="copyTracking">
              <i class="ti tabler-copy me-1"></i>Copy
            </button>
            <a class="btn btn-label-primary" href="{{ $trackingUrl }}" target="_blank" rel="noopener"
               aria-label="Open the tracking page">
              <i class="ti tabler-external-link"></i>
            </a>
          </div>
          @if($trip->phone_number)
            <a class="btn btn-sm btn-text-primary p-0 mt-2"
               href="sms:{{ $trip->phone_number }}?&body={{ rawurlencode('Track your ride: ' . $trackingUrl) }}">
              <i class="ti tabler-message me-1"></i>Open in messages to {{ $trip->phone_number }}
            </a>
          @endif
        @else
          <form method="POST" action="{{ route('dispatcher.trip.tracking-link', $trip->id) }}">
            @csrf
            <button type="submit" class="btn btn-label-primary">
              <i class="ti tabler-link-plus me-1"></i>Create link
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Row 1: Passenger Info & Route --}}
<div class="row g-4 mb-4">
  {{-- Passenger Info Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <small class="text-muted d-block">Member ID: {{ $trip->member_id ?: 'MB-' . (1000 + $trip->id) }}</small>
            <h5 class="fw-bold mb-1">{{ $trip->first_name }} {{ $trip->last_name }}</h5>
          </div>
          <div class="d-flex gap-1 flex-wrap">
            @if($trip->req_wheelchair)
              <span class="badge bg-label-warning rounded-pill"><i class="ti tabler-wheelchair me-1"></i>Wheelchair</span>
            @endif
            @if($trip->req_stretcher)
              <span class="badge bg-label-info rounded-pill"><i class="ti tabler-bed me-1"></i>Stretcher</span>
            @endif
            @if($trip->billing_type)
              <span class="badge bg-label-secondary rounded-pill text-capitalize">{{ $trip->billing_type }}</span>
            @endif
          </div>
        </div>

        <div class="mb-2 text-muted small">
          <i class="ti tabler-phone text-primary me-2"></i>{{ $trip->phone_number ?: 'N/A' }}
        </div>
        <div class="text-muted small">
          <i class="ti tabler-id text-primary me-2"></i>Member ID: {{ $trip->member_id ?: 'N/A' }}
        </div>
      </div>
    </div>
  </div>

  {{-- Route Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Route</h6>
          <div class="small text-muted">
            <span class="me-3"><i class="ti tabler-route text-primary me-1"></i>Est. Distance: {{ $trip->distance ? $trip->distance . ' mi' : 'N/A' }}</span>
          </div>
        </div>

        <div class="position-relative pt-1 pb-1">
          {{-- Dotted Line connecting pins --}}
          <div class="position-absolute" style="left: 14px; top: 15px; bottom: 15px; border-left: 2px dashed #cfd4dc; z-index: 1;"></div>

          {{-- Pickup --}}
          <div class="d-flex align-items-center gap-3 mb-3 position-relative" style="z-index: 2;">
            <div class="avatar avatar-sm flex-shrink-0">
              <span class="avatar-initial rounded-circle" style="background-color: #eeeffb; color: #435ebe; font-size: 13px;">
                <i class="ti tabler-map-pin"></i>
              </span>
            </div>
            <div>
              <small class="text-muted d-block" style="font-size: 0.82rem;">Pickup at {{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</small>
              <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;">{{ $trip->pickup_address }}</h6>
            </div>
          </div>

          {{-- Drop-Off --}}
          <div class="d-flex align-items-center gap-3 position-relative" style="z-index: 2;">
            <div class="avatar avatar-sm flex-shrink-0">
              <span class="avatar-initial rounded-circle" style="background-color: #eeeffb; color: #435ebe; font-size: 13px;">
                <i class="ti tabler-map-pin"></i>
              </span>
            </div>
            <div>
              <small class="text-muted d-block" style="font-size: 0.82rem;">Drop-Off</small>
              <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;">{{ $trip->dropoff_address }}</h6>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Row 2: Action Required / Notes Banner --}}
@if($trip->notes || $trip->req_wheelchair || $trip->req_stretcher || $trip->req_o2_tank)
<div class="card mb-4 bg-label-warning border-warning shadow-none">
  <div class="card-body p-3 d-flex align-items-start gap-3">
    <div class="avatar avatar-sm flex-shrink-0 bg-warning text-white rounded d-flex align-items-center justify-content-center">
      <i class="ti tabler-alert-triangle"></i>
    </div>
    <div>
      <h6 class="fw-bold mb-1 text-dark">Action Required / Special Instructions</h6>
      <p class="mb-0 small text-dark opacity-90">
        @if($trip->notes)
          {{ $trip->notes }}
        @else
          Client requires special assistance. Ensure proper equipment is prepared before pickup.
        @endif
      </p>
    </div>
  </div>
</div>
@endif

{{-- Row 3: Driver & Vehicle + Trip Log --}}
<div class="row g-4">
  {{-- Driver & Vehicle Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-lg">
              <span class="avatar-initial rounded-circle bg-label-primary fs-4 fw-bold">
                {{ $trip->driver ? strtoupper(substr($trip->driver->name, 0, 2)) : 'DR' }}
              </span>
            </div>
            <div>
              <h6 class="fw-bold mb-0">{{ $trip->driver ? $trip->driver->name : 'Unassigned Driver' }}</h6>
              <small class="text-success fw-semibold"><i class="ti tabler-circle-filled icon-xs me-1"></i>Online • Ready</small>
            </div>
          </div>
          <span class="badge bg-label-info">Driver & Vehicle</span>
        </div>

        <div class="mb-3 small">
          <div class="text-muted"><strong class="text-dark">Vehicle:</strong> {{ $trip->vehicle ? $trip->vehicle->name : 'Unassigned' }}</div>
          <div class="text-muted"><strong class="text-dark">License Plate:</strong> {{ $trip->vehicle ? ($trip->vehicle->license_plate ?: 'TX-VAN-4821') : 'N/A' }}</div>
        </div>

        <a href="tel:{{ $trip->driver ? $trip->driver->phone : '' }}" class="btn btn-primary w-100">
          <i class="ti tabler-message me-1"></i> Contact Driver
        </a>
      </div>
    </div>
  </div>

  {{-- Trip Log Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <h6 class="fw-bold mb-4">Trip Log</h6>

        <ul class="timeline ms-1 mb-0 list-unstyled">
          <li class="d-flex align-items-start mb-3">
            <span class="badge bg-success rounded-circle p-1 me-3 mt-1"><i class="ti tabler-check"></i></span>
            <div class="d-flex justify-content-between w-100">
              <div>
                <strong class="d-block small">Confirmed & Scheduled</strong>
                <small class="text-muted">Trip details registered in system</small>
              </div>
              <small class="text-muted">{{ $trip->created_at->format('h:i A') }}</small>
            </div>
          </li>

          <li class="d-flex align-items-start mb-3">
            <span class="badge bg-primary rounded-circle p-1 me-3 mt-1"><i class="ti tabler-bell"></i></span>
            <div class="d-flex justify-content-between w-100">
              <div>
                <strong class="d-block small">Driver Assigned</strong>
                <small class="text-muted">{{ $trip->driver ? $trip->driver->name : 'Pending assignment' }}</small>
              </div>
              <small class="text-muted">{{ $trip->created_at->addMinutes(5)->format('h:i A') }}</small>
            </div>
          </li>

          <li class="d-flex align-items-start">
            <span class="badge bg-warning rounded-circle p-1 me-3 mt-1"><i class="ti tabler-car"></i></span>
            <div class="d-flex justify-content-between w-100">
              <div>
                <strong class="d-block small">Status: {{ $trip->statusEnum()?->label() }}</strong>
                <small class="text-muted">Current status update</small>
              </div>
              <small class="text-muted">{{ $trip->updated_at->format('h:i A') }}</small>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>


{{-- ── Pre-trip inspection ────────────────────────────────────────────────
     The driver fills one of these in per day, not per trip, so what is shown
     here is their check for the day this trip runs. It answers the question an
     audit asks — was the vehicle checked before this journey — without having
     to leave the trip. --}}
@if($trip->driver_id)
  @php($responses = $inspection ? $inspection->responses : collect())
  @php($defects = $responses->filter(fn ($r) => $r->status === App\Enums\InspectionItemStatus::Fail))
  @php($passed = $responses->filter(fn ($r) => $r->status === App\Enums\InspectionItemStatus::Pass)->count())
  @php($pending = $responses->filter(fn ($r) => $r->status === App\Enums\InspectionItemStatus::Pending)->count())

  <div class="card mt-4 shadow-sm border-0">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
      <div class="d-flex align-items-center gap-2">
        <i class="ti tabler-clipboard-check text-primary"></i>
        <div>
          <h5 class="mb-0">Pre-Trip Inspection</h5>
          <small class="text-muted">
            {{ $trip->driver?->name }} &middot;
            {{ \Carbon\Carbon::parse($trip->pickup_date)->format('D, d M Y') }}
          </small>
        </div>
      </div>

      @if(! $inspection)
        <span class="badge bg-label-secondary">Not done</span>
      @elseif($inspection->has_defects)
        <span class="badge bg-label-danger">
          {{ $defects->count() }} {{ \Illuminate\Support\Str::plural('defect', $defects->count()) }} reported
        </span>
      @elseif($inspection->isSubmitted())
        <span class="badge bg-label-success">Passed &mdash; cleared to drive</span>
      @else
        <span class="badge bg-label-warning">In progress</span>
      @endif
    </div>

    @if(! $inspection)
      <div class="card-body text-center py-5">
        <i class="ti tabler-clipboard-off icon-48px text-muted d-block mb-2"></i>
        <h6 class="mb-1">No inspection on record for this date</h6>
        <p class="text-muted mb-0 small">
          {{ $trip->driver?->name }} did not complete a pre-trip inspection on
          {{ \Carbon\Carbon::parse($trip->pickup_date)->format('d M Y') }}.
        </p>
      </div>
    @else
      {{-- Defects first: the only part of this anyone needs in a hurry. --}}
      @if($defects->isNotEmpty())
        <div class="card-body border-bottom pb-3">
          <div class="alert alert-danger inspection-defects mb-0">
            <h6 class="alert-heading mb-2">
              <i class="ti tabler-alert-triangle me-1"></i>Defects reported
            </h6>
            <ul class="mb-0 ps-3">
              @foreach($defects as $defect)
                <li>
                  <strong>{{ $defect->item?->label ?? 'Checklist item' }}</strong>
                  @if($defect->note) &mdash; {{ $defect->note }} @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      <div class="card-body border-bottom">
        <div class="row g-3 text-center">
          <div class="col-6 col-md-3">
            <h5 class="mb-0 text-success">{{ $passed }}</h5>
            <small class="text-muted">Passed</small>
          </div>
          <div class="col-6 col-md-3">
            <h5 class="mb-0 {{ $defects->count() ? 'text-danger' : '' }}">{{ $defects->count() }}</h5>
            <small class="text-muted">Defects</small>
          </div>
          <div class="col-6 col-md-3">
            <h5 class="mb-0 {{ $pending ? 'text-warning' : '' }}">{{ $pending }}</h5>
            <small class="text-muted">Not checked</small>
          </div>
          <div class="col-6 col-md-3">
            <h5 class="mb-0">{{ $inspection->vehicle?->name ?? '&mdash;' }}</h5>
            <small class="text-muted">Vehicle inspected</small>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Checklist item</th>
              <th style="width: 10rem;">Result</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            @forelse($inspection->responses->sortBy(fn ($r) => $r->item?->sort_order ?? 999) as $response)
              <tr>
                <td class="fw-medium text-heading">{{ $response->item?->label ?? 'Checklist item' }}</td>
                <td>
                  @switch($response->status)
                    @case(App\Enums\InspectionItemStatus::Pass)
                      <span class="badge bg-label-success">
                        <i class="ti tabler-check me-1"></i>Pass
                      </span>
                      @break
                    @case(App\Enums\InspectionItemStatus::Fail)
                      <span class="badge bg-label-danger">
                        <i class="ti tabler-alert-triangle me-1"></i>Defect
                      </span>
                      @break
                    @default
                      <span class="badge bg-label-secondary">
                        <i class="ti tabler-clock me-1"></i>Not checked
                      </span>
                  @endswitch
                </td>
                <td class="text-muted">{{ $response->note ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No checklist items recorded.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 border-top">
        <small class="text-muted">
          @if($inspection->submitted_at)
            <i class="ti tabler-circle-check me-1"></i>
            Signed off {{ $inspection->submitted_at->format('d M Y, h:i A') }}
          @else
            <i class="ti tabler-progress me-1"></i>
            Started but not signed off yet
          @endif
        </small>

        @if($signatureUrl)
          {{-- Signature sits on a private disk, so this link is temporary. --}}
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted">Driver signature</small>
            <a href="{{ $signatureUrl }}" target="_blank" rel="noopener"
               class="border rounded px-2 py-1 bg-white d-inline-flex align-items-center">
              <img src="{{ $signatureUrl }}" alt="Driver signature" style="height: 38px;" />
            </a>
          </div>
        @endif
      </div>
    @endif
  </div>
@endif

@endsection

@section('page-script')
<script>
  (function () {
    var button = document.getElementById('copyTracking');
    var field  = document.getElementById('trackingLink');
    if (!button || !field) return;

    button.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(field.value);
      } catch (error) {
        // Older browsers, or a page not served over HTTPS.
        field.select();
        document.execCommand('copy');
      }

      var original = button.innerHTML;
      button.innerHTML = '<i class="ti tabler-check me-1"></i>Copied';
      setTimeout(function () { button.innerHTML = original; }, 1800);
    });
  })();
</script>
@endsection
