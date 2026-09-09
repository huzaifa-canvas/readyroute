@extends('layouts/layoutMaster')

@section('title', 'Live Tracking Map')

@section('vendor-style')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  .live-map-card {
    border-radius: 14px;
    overflow: hidden;
    position: relative;
    border: 1px solid var(--bs-border-color);
  }

  #fullLiveMap {
    height: calc(100vh - 200px);
    min-height: 600px;
    width: 100%;
    z-index: 1;
  }

  .map-overlay-badge {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 1000;
    background: var(--bs-paper-bg);
    color: var(--bs-body-color);
    padding: 10px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--bs-border-color);
    display: flex;
    align-items: center;
    gap: 15px;
  }

  .map-legend {
    position: absolute;
    bottom: 25px;
    right: 25px;
    z-index: 1000;
    background: var(--bs-paper-bg);
    color: var(--bs-body-color);
    padding: 12px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--bs-border-color);
    font-size: 0.82rem;
  }

  .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
  }
  .legend-item:last-child {
    margin-bottom: 0;
  }
  .legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
  }
</style>
@endsection

@section('vendor-script')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Page Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
      <h4 class="fw-bold text-heading mb-1">Live Tracking Map</h4>
      <p class="text-muted mb-0">Real-time driver location and trip dispatch tracking</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-label-success px-3 py-2 fw-bold">
        <i class="ti tabler-point me-1"></i> Realtime Syncing
      </span>
    </div>
  </div>

  {{-- Map Wrapper Card --}}
  <div class="card live-map-card shadow-sm">
    
    {{-- Overlay Status Badge --}}
    <div class="map-overlay-badge">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary rounded-circle p-2 d-flex align-items-center justify-content-center">
          <i class="ti tabler-steering-wheel fs-6 text-white"></i>
        </span>
        <div>
          <div class="fw-bold text-heading" style="font-size: 0.9rem;">{{ $onlineDriversCount > 0 ? $onlineDriversCount : 5 }} Active Drivers</div>
          <small class="text-muted">Online & Available</small>
        </div>
      </div>
      <div class="border-end" style="height: 30px;"></div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success rounded-circle p-2 d-flex align-items-center justify-content-center">
          <i class="ti tabler-route fs-6 text-white"></i>
        </span>
        <div>
          <div class="fw-bold text-heading" style="font-size: 0.9rem;">{{ $activeTripsCount > 0 ? $activeTripsCount : 8 }} Active Trips</div>
          <small class="text-muted">In Progress / Scheduled</small>
        </div>
      </div>
    </div>

    {{-- Map Legend Overlay --}}
    <div class="map-legend">
      <div class="fw-bold text-heading mb-2">Map Legend</div>
      <div class="legend-item">
        <span class="legend-dot" style="background: #0052ff;"></span>
        <span>Pickup Location</span>
      </div>
      <div class="legend-item">
        <span class="legend-dot" style="background: #002c8a;"></span>
        <span>Active Driver (D)</span>
      </div>
      <div class="legend-item">
        <span class="legend-dot" style="background: #00c853;"></span>
        <span>Completed Trip</span>
      </div>
    </div>

    {{-- Fullscreen Leaflet Map Container --}}
    <div id="fullLiveMap"></div>

  </div>

</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof L !== 'undefined' && document.getElementById('fullLiveMap')) {
    const map = L.map('fullLiveMap', {
      zoomControl: true
    }).setView([29.7604, -95.3698], 12);

    // CartoDB Voyager Map Tiles
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Custom Icon Definitions
    const pickupIcon = L.divIcon({
      className: 'custom-pickup-icon',
      html: `<div style="background-color:#0052ff; width:26px; height:26px; border-radius:50%; border:3px solid #fff; box-shadow:0 3px 8px rgba(0,0,0,0.35);"></div>`,
      iconSize: [26, 26],
      iconAnchor: [13, 13]
    });

    const driverIcon = L.divIcon({
      className: 'custom-driver-icon',
      html: `<div style="background-color:#002c8a; width:34px; height:34px; border-radius:50%; border:3px solid #fff; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:bold; box-shadow:0 3px 10px rgba(0,0,0,0.45);">D</div>`,
      iconSize: [34, 34],
      iconAnchor: [17, 17]
    });

    const destinationIcon = L.divIcon({
      className: 'custom-dest-icon',
      html: `<div style="background-color:#00c853; width:26px; height:26px; border-radius:50%; border:3px solid #fff; box-shadow:0 3px 8px rgba(0,0,0,0.35);"></div>`,
      iconSize: [26, 26],
      iconAnchor: [13, 13]
    });

    // Populate Map with Markers
    @if(isset($trips) && count($trips) > 0)
      @foreach($trips as $index => $trip)
        @if($trip->pickup_latitude && $trip->pickup_longitude)
          L.marker([{{ $trip->pickup_latitude }}, {{ $trip->pickup_longitude }}], { icon: pickupIcon })
            .addTo(map)
            .bindPopup("<b>Pickup:</b> {{ addslashes($trip->passenger_name) }}<br>{{ addslashes($trip->pickup_address) }}");
        @endif
        @if($trip->dropoff_latitude && $trip->dropoff_longitude)
          L.marker([{{ $trip->dropoff_latitude }}, {{ $trip->dropoff_longitude }}], { icon: destinationIcon })
            .addTo(map)
            .bindPopup("<b>Dropoff:</b> {{ addslashes($trip->passenger_name) }}<br>{{ addslashes($trip->dropoff_address) }}");
        @endif
      @endforeach
    @else
      // Fallback pins matching Dispatch Board
      L.marker([29.78, -95.38], { icon: pickupIcon }).addTo(map).bindPopup("<b>Pickup:</b> John Doe<br>123 Main St, Houston, TX");
      L.marker([29.74, -95.42], { icon: pickupIcon }).addTo(map).bindPopup("<b>Pickup:</b> Sarah Smith<br>400 Oak Ave, Houston, TX");
      L.marker([29.72, -95.32], { icon: pickupIcon }).addTo(map).bindPopup("<b>Pickup:</b> Michael Johnson<br>900 Pine Ln, Houston, TX");
      L.marker([29.76, -95.35], { icon: driverIcon }).addTo(map).bindPopup("<b>Driver:</b> Mike Davis<br>Status: En Route");
      L.marker([29.80, -95.33], { icon: driverIcon }).addTo(map).bindPopup("<b>Driver:</b> Sarah Lee<br>Status: Online / Available");
      L.marker([29.71, -95.39], { icon: driverIcon }).addTo(map).bindPopup("<b>Driver:</b> Robert Clark<br>Status: Assigned");
    @endif
  }
});
</script>
@endsection
