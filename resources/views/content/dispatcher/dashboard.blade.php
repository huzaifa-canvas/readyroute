@extends('layouts/layoutMaster')

@section('title', 'Dispatch Board')

@section('vendor-style')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  /* Map pins, route lines and the accent furniture below all follow the
     Primary Color chosen in the customizer. --rr-accent-deep is the one
     derived shade: it keeps the driver pin distinguishable from the pickup
     pin whatever the accent is. */
  :root {
    --rr-accent-deep: color-mix(in srgb, var(--bs-primary) 62%, #000000);
    --rr-accent-mid: color-mix(in srgb, var(--bs-primary) 78%, #000000);
    --rr-accent-hover: color-mix(in srgb, var(--bs-primary) 85%, #000000);
  }
  .metric-card {
    border-radius: 12px;
    box-shadow: 0 4px 18px 0 rgba(0, 0, 0, 0.05);
    border: none;
    transition: transform 0.2s ease;
    background: var(--bs-paper-bg);
  }
  .metric-card:hover {
    transform: translateY(-2px);
  }
  .metric-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: var(--bs-primary);
    color: var(--bs-primary-contrast, #ffffff);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
  }
  .trip-card {
    border-radius: 12px;
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 2px 10px 0 rgba(0, 0, 0, 0.03);
    background: var(--bs-paper-bg);
    padding: 20px;
    margin-bottom: 16px;
  }
  .btn-assign-blue {
    background-color: var(--bs-primary);
    color: var(--bs-primary-contrast, #ffffff);
    font-weight: 600;
    border-radius: 8px;
    padding: 10px;
    border: none;
    width: 100%;
    transition: background-color 0.2s ease;
  }
  .btn-assign-blue:hover {
    background-color: var(--rr-accent-hover);
    color: var(--bs-primary-contrast, #ffffff);
  }
  .assigned-badge {
    background-color: #fef3d6;
    color: #ab7a1c;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
  }
  .avatar-group-overlap .avatar {
    margin-left: -0.6rem;
    border: 2px solid var(--bs-paper-bg);
  }
  #dispatchLiveMap {
    height: 480px;
    width: 100%;
    border-radius: 12px;
    z-index: 1;
  }
  .map-overlay-pill {
    position: absolute;
    top: 15px;
    left: 15px;
    z-index: 1000;
    background: var(--bs-paper-bg);
    color: var(--bs-body-color);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
  }
  .map-overlay-fullscreen {
    position: absolute;
    bottom: 20px;
    left: 20px;
    z-index: 1000;
  }
</style>
@endsection

@section('vendor-script')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endsection

@section('content')

{{-- Alert Messages --}}
@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

{{-- Top Row: Stat Metrics --}}
<div class="row g-4 mb-4">
  {{-- Card 1: Active Trips --}}
  <div class="col-md-4">
    <div class="card metric-card p-3">
      <div class="card-body p-2 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted fw-semibold d-block mb-1 fs-6">Active Trips</span>
          <div class="d-flex align-items-baseline gap-2">
            <h2 class="fw-bold mb-0 text-heading">{{ $activeTripsCount }}</h2>
            <span class="badge bg-label-success rounded-pill small"><i class="ti tabler-arrow-up me-1"></i>12%</span>
          </div>
        </div>
        <div class="metric-icon-circle">
          <i class="ti tabler-car"></i>
        </div>
      </div>
    </div>
  </div>

  {{-- Card 2: Drivers Online --}}
  <div class="col-md-4">
    <div class="card metric-card p-3">
      <div class="card-body p-2 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted fw-semibold d-block mb-1 fs-6">Drivers Online</span>
          <div class="d-flex align-items-center gap-3">
            <h2 class="fw-bold mb-0 text-heading">{{ $driversOnlineCount }}/{{ $driversTotalCount }}</h2>
            @if($totalDrivers->count() > 0)
              <div class="d-flex align-items-center ms-1">
                @foreach($totalDrivers->take(3) as $d)
                  @php
                    $initials = collect(explode(' ', $d->name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->join('');
                  @endphp
                  <div class="avatar avatar-xs me-1" title="{{ $d->name }}">
                    <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold" style="font-size: 0.65rem;">
                      {{ substr($initials, 0, 2) }}
                    </span>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
        <div class="metric-icon-circle">
          <i class="ti tabler-user-check"></i>
        </div>
      </div>
    </div>
  </div>

  {{-- Card 3: Pending Assignments --}}
  <div class="col-md-4">
    <div class="card metric-card p-3">
      <div class="card-body p-2 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted fw-semibold d-block mb-1 fs-6">Pending Assignments</span>
          <h2 class="fw-bold mb-0 text-heading">{{ $pendingAssignmentsCount }}</h2>
        </div>
        <div class="metric-icon-circle">
          <i class="ti tabler-clock"></i>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Main Content: Next Upcoming Trips & Live Map Preview --}}
<div class="row g-4">
  {{-- Left Column: Next Upcoming Trips --}}
  <div class="col-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0 text-heading">Next Upcoming Trips</h5>
      <a href="{{ route('dispatcher.trip.list') }}" class="text-primary fw-semibold small text-decoration-underline">View All</a>
    </div>

    @forelse($upcomingTrips as $trip)
      <div class="trip-card">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="fw-bold mb-0 text-heading fs-5">{{ $trip->first_name }} {{ $trip->last_name }}</h6>
          <div class="d-flex align-items-center gap-2">
            @if($trip->driver)
              <span class="assigned-badge">Assigned: {{ $trip->driver->name }}</span>
            @endif
            <small class="text-muted"><i class="ti tabler-clock me-1"></i>{{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</small>
          </div>
        </div>

        <div class="mb-2 text-muted small">
          <i class="ti tabler-map-pin text-primary me-2"></i>Pickup: {{ $trip->pickup_address }}
        </div>

        <div class="mb-3">
          <span class="badge bg-label-secondary text-capitalize rounded-pill px-3">
            @if($trip->req_wheelchair)
              <i class="ti tabler-wheelchair me-1"></i>
            @endif
            {{ $trip->billing_type ?: 'No billing type' }} {{ $trip->req_wheelchair ? '• Wheelchair' : '' }}
          </span>
        </div>

        @if(!$trip->driver_id)
          <button type="button" class="btn btn-assign-blue" onclick="openAssignModal({{ $trip->id }}, '{{ $trip->first_name }} {{ $trip->last_name }}')">
            Assign Driver
          </button>
        @endif
      </div>
    @empty
      {{-- A new company has nothing booked yet. Show that plainly instead of
           demo passengers, which read as real trips someone has to action. --}}
      <div class="trip-card text-center py-5">
        <i class="ti tabler-calendar-off text-secondary d-block mb-2" style="font-size: 2.5rem;"></i>
        <h6 class="fw-bold mb-1">No upcoming trips</h6>
        <p class="text-muted small mb-3">
          Trips you schedule appear here, soonest first, ready to assign to a driver.
        </p>
        @if(auth()->user()->canUse('trips', 'trips.create') && auth()->user()->hasActiveSubscription())
          <a href="{{ route('dispatcher.trip.create') }}" class="btn btn-assign-blue d-inline-block px-4">
            Create the first trip
          </a>
        @elseif(! auth()->user()->hasActiveSubscription())
          <a href="{{ route('dispatcher.subscription') }}" class="btn btn-assign-blue d-inline-block px-4">
            Choose a plan to get started
          </a>
        @endif
      </div>
    @endforelse
  </div>

  {{-- Right Column: Live Map Preview --}}
  <div class="col-lg-7">
    <h5 class="fw-bold mb-3 text-heading">Live Map Preview</h5>

    <div class="card shadow-sm border-0 position-relative">
      <div class="map-overlay-pill">
        Expand Live Map (expand Live Map)
      </div>

      <div id="dispatchLiveMap"></div>

      <div class="map-overlay-fullscreen">
        <a href="{{ route('dispatcher.live-map') }}" class="btn btn-primary fw-bold px-4 py-2" style="border-radius: 8px;">
          <i class="ti tabler-maximize me-1"></i> Enter Fullscreen
        </a>
      </div>
    </div>
  </div>
</div>

{{-- Assign Driver Modal --}}
<div class="modal fade" id="assignDriverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="assignDriverForm" method="POST" action="">
        @csrf
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold">Assign Driver to <span id="modalPassengerTitle">Trip</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body py-4">
          <div class="mb-3">
            <label for="modal_driver_id" class="form-label fw-semibold">Select Available Driver</label>
            <select name="driver_id" id="modal_driver_id" class="form-select" required>
              <option value="">-- Select Driver --</option>
              @foreach($totalDrivers as $driver)
                <option value="{{ $driver->id }}">{{ $driver->name }} (Available)</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Assign Driver</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
let assignModal;

document.addEventListener('DOMContentLoaded', function() {
  assignModal = new bootstrap.Modal(document.getElementById('assignDriverModal'));

  // Initialize Leaflet Live Map Preview matching reference screenshot
  if (typeof L !== 'undefined' && document.getElementById('dispatchLiveMap')) {
    const map = L.map('dispatchLiveMap', {
      zoomControl: true
    // Somewhere to start before any real point is known; the view is moved
    // to the company's own pickups and drivers as soon as there are any.
    }).setView([29.7604, -95.3698], 11);

    // Tile Layer
    // Basemap comes from config, so moving off OpenStreetMap later is an
    // .env change rather than an edit to every map in the panel.
    @php($tiles = \App\Support\MapTiles::current())
    L.tileLayer(@json($tiles['url']), {
      maxZoom: {{ $tiles['max_zoom'] }},
      attribution: @json($tiles['attribution'])
    }).addTo(map);

    // Custom Pins matching reference screenshot layout
    const blueIcon = L.divIcon({
      className: 'custom-div-icon',
      html: `<div style="background-color:var(--bs-primary); width:24px; height:24px; border-radius:50%; border:3px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>`,
      iconSize: [24, 24],
      iconAnchor: [12, 12]
    });

    const driverIcon = L.divIcon({
      className: 'custom-driver-icon',
      html: `<div style="background-color:var(--rr-accent-deep); width:30px; height:30px; border-radius:50%; border:3px solid #fff; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold; box-shadow:0 2px 8px rgba(0,0,0,0.4);">D</div>`,
      iconSize: [30, 30],
      iconAnchor: [15, 15]
    });

    // Real pickups and real driver positions only. An empty map is the
    // honest answer for a company with nothing booked.
    const pickups = @json($mapPoints['pickups']);
    const drivers = @json($mapPoints['drivers']);
    const bounds  = [];

    pickups.forEach(function (point) {
      L.marker([point.lat, point.lng], { icon: blueIcon }).addTo(map).bindPopup(point.label);
      bounds.push([point.lat, point.lng]);
    });

    drivers.forEach(function (point) {
      L.marker([point.lat, point.lng], { icon: driverIcon }).addTo(map).bindPopup(point.label);
      bounds.push([point.lat, point.lng]);
    });

    if (bounds.length > 1) {
      map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40] });
    } else if (bounds.length === 1) {
      map.setView(bounds[0], 13);
    } else {
      // Nothing to show: say so over the map rather than leaving a blank
      // rectangle that looks broken.
      const note = L.control({ position: 'topright' });
      note.onAdd = function () {
        const div = L.DomUtil.create('div', 'leaflet-bar');
        div.style.cssText = 'background:var(--bs-paper-bg);padding:.5rem .75rem;font-size:.8125rem;color:var(--bs-body-color);border-radius:.375rem;';
        div.textContent = 'No trips or driver positions yet';
        return div;
      };
      note.addTo(map);
    }
  }
});

function openAssignModal(tripId, passengerName) {
  document.getElementById('modalPassengerTitle').textContent = passengerName;
  document.getElementById('assignDriverForm').action = "{{ url('dispatcher/trip/assign') }}/" + tripId;
  assignModal.show();
}
</script>
@endsection
