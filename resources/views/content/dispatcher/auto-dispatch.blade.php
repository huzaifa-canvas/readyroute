@extends('layouts/layoutMaster')

@section('title', 'Smart Auto-Dispatch')

@section('vendor-style')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  .unassigned-trip-card {
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.07);
    box-shadow: 0 2px 10px 0 rgba(0, 0, 0, 0.03);
    background: #ffffff;
    padding: 18px 20px;
    margin-bottom: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .unassigned-trip-card:hover {
    border-color: #0052ff;
    box-shadow: 0 4px 14px rgba(0, 82, 255, 0.08);
  }
  .unassigned-trip-card.active-trip-card {
    border-left: 4px solid #0052ff !important;
    background-color: #f6f8fe !important;
    box-shadow: 0 4px 16px rgba(0, 82, 255, 0.12);
  }
  .badge-needs-assignment {
    background-color: #fde8e8;
    color: #e53e3e;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
  }
  .badge-assigned-driver {
    background-color: #fef3d6;
    color: #ab7a1c;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
  }
  #autoDispatchMap {
    height: 550px;
    width: 100%;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
    z-index: 1;
  }
  .btn-optimize {
    background-color: #0052ff;
    color: #ffffff;
    font-weight: 700;
    border-radius: 8px;
    padding: 10px 24px;
    border: none;
    font-size: 0.95rem;
    transition: background-color 0.2s ease;
  }
  .btn-optimize:hover {
    background-color: #0040cd;
    color: #ffffff;
  }
  .filter-pill-btn {
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.82rem;
    padding: 6px 16px;
    transition: all 0.2s ease;
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
    <i class="ti tabler-circle-check me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('info'))
  <div class="alert alert-info alert-dismissible mb-4" role="alert">
    <i class="ti tabler-info-circle me-2"></i>{{ session('info') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    <i class="ti tabler-alert-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

{{-- Header Row: Page Title & Optimize Button --}}
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="fw-bold text-dark mb-1">Smart Auto-Dispatch</h3>
    <p class="text-muted small mb-0">Select any trip to view live route or run auto-optimization</p>
  </div>
  
  <form action="{{ route('dispatcher.auto-dispatch.optimize') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-optimize shadow-sm">
      <i class="ti tabler-wand me-2"></i>Optimize {{ $unassignedCount }} Unassigned Trips
    </button>
  </form>
</div>

{{-- Main Grid: Trip Pool & Route Map --}}
<div class="row g-4">
  {{-- Left Column: Trip Pool --}}
  <div class="col-lg-5">
    
    {{-- Top Filter Pills Bar --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold text-dark mb-0">Trip Pool</h5>

      {{-- Interactive Filter Buttons --}}
      <div class="btn-group btn-group-sm" role="group" aria-label="Trip Status Filter">
        <button type="button" class="btn btn-primary filter-pill-btn active" onclick="filterTrips('all', this)">
          All ({{ $allTrips->count() }})
        </button>
        <button type="button" class="btn btn-outline-danger filter-pill-btn" onclick="filterTrips('unassigned', this)">
          Unassigned ({{ $unassignedCount }})
        </button>
        <button type="button" class="btn btn-outline-warning filter-pill-btn" onclick="filterTrips('assigned', this)">
          Assigned ({{ $assignedCount }})
        </button>
      </div>
    </div>

    <div class="pe-1" style="max-height: 550px; overflow-y: auto;">
      @forelse($allTrips as $index => $trip)
        @php
          $isAssigned = $trip->driver_id ? true : false;
          $pLat = $trip->pickup_lat ?: (29.7604 + ($index * 0.03));
          $pLng = $trip->pickup_lng ?: (-95.3698 + ($index * 0.04));
          $dLat = $trip->dropoff_lat ?: ($pLat + 0.04);
          $dLng = $trip->dropoff_lng ?: ($pLng + 0.06);
          $tripNum = 801 + $index;
        @endphp

        <div class="unassigned-trip-card trip-item {{ $index === 0 ? 'active-trip-card' : '' }}"
             data-status="{{ $isAssigned ? 'assigned' : 'unassigned' }}"
             onclick="selectTripRoute(this, {{ $pLat }}, {{ $pLng }}, {{ $dLat }}, {{ $dLng }}, 'Trip #{{ $tripNum }} — {{ $trip->first_name }} {{ $trip->last_name }}', '{{ $trip->pickup_address }}', '{{ $trip->dropoff_address }}')">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0 fs-5">Trip #{{ $tripNum }} — {{ $trip->first_name }} {{ $trip->last_name }}</h6>
            @if($isAssigned)
              <span class="badge-assigned-driver">Assigned: {{ $trip->driver ? $trip->driver->name : 'Driver' }}</span>
            @else
              <span class="badge-needs-assignment">Needs Assignment</span>
            @endif
          </div>

          <div class="d-flex align-items-center gap-3 text-muted small">
            <span><i class="ti tabler-clock text-muted me-1"></i>{{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</span>
            <span><i class="ti tabler-map-pin text-primary me-1"></i>{{ $trip->distance ? $trip->distance . ' miles' : '4.2 miles' }}</span>
            <span>
              <i class="ti {{ $trip->req_wheelchair ? 'tabler-wheelchair' : 'tabler-user' }} text-primary me-1"></i>
              {{ $trip->req_wheelchair ? 'Wheelchair Req.' : 'Standard' }}
            </span>
          </div>
        </div>
      @empty
        {{-- Demonstration Pool if database is empty --}}
        <div class="unassigned-trip-card trip-item active-trip-card" data-status="unassigned"
             onclick="selectTripRoute(this, 29.7804, -95.3698, 29.8404, -95.2998, 'Trip #801 — Patient Name', '123 Main St', '456 Hospital Blvd')">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0 fs-5">Trip #801 — Patient Name</h6>
            <span class="badge-needs-assignment">Needs Assignment</span>
          </div>
          <div class="d-flex align-items-center gap-3 text-muted small">
            <span><i class="ti tabler-clock me-1"></i>10:30 AM</span>
            <span><i class="ti tabler-map-pin text-primary me-1"></i>4.2 miles</span>
            <span><i class="ti tabler-wheelchair text-primary me-1"></i>Wheelchair Req.</span>
          </div>
        </div>

        <div class="unassigned-trip-card trip-item" data-status="assigned"
             onclick="selectTripRoute(this, 29.7404, -95.4298, 29.7904, -95.3498, 'Trip #802 — Maria Garcia', '400 Oak Ave', 'St. Mary\'s Clinic')">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0 fs-5">Trip #802 — Maria Garcia</h6>
            <span class="badge-assigned-driver">Assigned: Mike Davis</span>
          </div>
          <div class="d-flex align-items-center gap-3 text-muted small">
            <span><i class="ti tabler-clock me-1"></i>11:00 AM</span>
            <span><i class="ti tabler-map-pin text-primary me-1"></i>5.1 miles</span>
            <span><i class="ti tabler-wheelchair text-primary me-1"></i>Wheelchair Req.</span>
          </div>
        </div>

        <div class="unassigned-trip-card trip-item" data-status="unassigned"
             onclick="selectTripRoute(this, 29.7204, -95.3298, 29.7704, -95.2698, 'Trip #803 — Patient Name', '900 Pine Ln', 'Dialysis Center')">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0 fs-5">Trip #803 — Patient Name</h6>
            <span class="badge-needs-assignment">Needs Assignment</span>
          </div>
          <div class="d-flex align-items-center gap-3 text-muted small">
            <span><i class="ti tabler-clock me-1"></i>11:30 AM</span>
            <span><i class="ti tabler-map-pin text-primary me-1"></i>3.8 miles</span>
            <span><i class="ti tabler-user text-primary me-1"></i>Standard</span>
          </div>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Right Column: Live Map Container --}}
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <div id="autoDispatchMap"></div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
let map;
let activePolyline = null;
let activeMarkers = [];

document.addEventListener('DOMContentLoaded', function() {
  // Initialize Leaflet Map
  if (typeof L !== 'undefined' && document.getElementById('autoDispatchMap')) {
    map = L.map('autoDispatchMap', {
      zoomControl: true
    }).setView([29.7604, -95.3698], 11);

    // Light map tile theme
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Auto-select first trip card to draw initial route
    const firstCard = document.querySelector('.unassigned-trip-card.active-trip-card');
    if (firstCard) {
      firstCard.click();
    }
  }
});

// Dynamic Filter (All / Unassigned / Assigned)
function filterTrips(type, btnElement) {
  // Update button active state
  document.querySelectorAll('.filter-pill-btn').forEach(b => {
    b.classList.remove('active', 'btn-primary');
    if (!b.classList.contains('btn-outline-danger') && !b.classList.contains('btn-outline-warning')) {
      b.classList.add('btn-outline-primary');
    }
  });

  btnElement.classList.add('active');

  const items = document.querySelectorAll('.trip-item');
  let firstVisible = null;

  items.forEach(item => {
    const status = item.getAttribute('data-status');
    if (type === 'all' || status === type) {
      item.style.display = 'block';
      if (!firstVisible) firstVisible = item;
    } else {
      item.style.display = 'none';
    }
  });

  // Select first visible trip in filtered list
  if (firstVisible) {
    firstVisible.click();
  }
}

function selectTripRoute(cardElement, pLat, pLng, dLat, dLng, tripTitle, pickupAddr, dropoffAddr) {
  // Update active card styling
  document.querySelectorAll('.unassigned-trip-card').forEach(c => c.classList.remove('active-trip-card'));
  if (cardElement) {
    cardElement.classList.add('active-trip-card');
  }

  if (!map) return;

  // Clear previous layers
  if (activePolyline) {
    map.removeLayer(activePolyline);
    activePolyline = null;
  }
  activeMarkers.forEach(m => map.removeLayer(m));
  activeMarkers = [];

  const lat1 = parseFloat(pLat) || 29.7604;
  const lng1 = parseFloat(pLng) || -95.3698;
  const lat2 = parseFloat(dLat) || (lat1 + 0.04);
  const lng2 = parseFloat(dLng) || (lng1 + 0.05);

  // Blue Pin Marker Icons
  const pickupMarkerIcon = L.divIcon({
    className: 'custom-pickup-pin',
    html: `<div style="background-color:#0052ff; width:34px; height:34px; border-radius:50%; border:3px solid #fff; color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(0,82,255,0.4);"><i class="ti tabler-map-pin" style="font-size:18px;"></i></div>`,
    iconSize: [34, 34],
    iconAnchor: [17, 17]
  });

  const dropoffMarkerIcon = L.divIcon({
    className: 'custom-dropoff-pin',
    html: `<div style="background-color:#0038a8; width:34px; height:34px; border-radius:50%; border:3px solid #fff; color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(0,0,0,0.3);"><i class="ti tabler-map-pin" style="font-size:18px;"></i></div>`,
    iconSize: [34, 34],
    iconAnchor: [17, 17]
  });

  // Create Pickup & Dropoff Markers
  const m1 = L.marker([lat1, lng1], { icon: pickupMarkerIcon }).addTo(map).bindPopup(`<strong>Pickup (${tripTitle}):</strong> ${pickupAddr}`);
  const m2 = L.marker([lat2, lng2], { icon: dropoffMarkerIcon }).addTo(map).bindPopup(`<strong>Drop-Off (${tripTitle}):</strong> ${dropoffAddr}`);
  activeMarkers.push(m1, m2);

  // Draw Route Polyline Line matching screenshot
  const midLat = lat1 + (lat2 - lat1) * 0.5;
  const midLng = lng1 + (lng2 - lng1) * 0.2;

  const routeCoords = [
    [lat1, lng1],
    [midLat, midLng],
    [lat2, lng2]
  ];

  activePolyline = L.polyline(routeCoords, {
    color: '#0052ff',
    weight: 5,
    opacity: 0.9,
    lineJoin: 'round'
  }).addTo(map);

  // Zoom map to show route
  const bounds = L.latLngBounds([[lat1, lng1], [lat2, lng2]]);
  map.fitBounds(bounds, { padding: [60, 60] });
}
</script>
@endsection
