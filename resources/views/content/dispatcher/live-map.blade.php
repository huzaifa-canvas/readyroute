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
          <div class="fw-bold text-heading" style="font-size: 0.9rem;">{{ $onlineDriversCount }} Active {{ $onlineDriversCount === 1 ? 'Driver' : 'Drivers' }}</div>
          <small class="text-muted">Online & Available</small>
        </div>
      </div>
      <div class="border-end" style="height: 30px;"></div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success rounded-circle p-2 d-flex align-items-center justify-content-center">
          <i class="ti tabler-route fs-6 text-white"></i>
        </span>
        <div>
          <div class="fw-bold text-heading" style="font-size: 0.9rem;">{{ $activeTripsCount }} {{ $activeTripsCount === 1 ? 'Active Trip' : 'Active Trips' }}</div>
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
    // Basemap comes from config, so moving off OpenStreetMap later is an
    // .env change rather than an edit to every map in the panel.
    @php($tiles = \App\Support\MapTiles::current())
    L.tileLayer(@json($tiles['url']), {
      maxZoom: {{ $tiles['max_zoom'] }},
      attribution: @json($tiles['attribution'])
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

    /*
     * Real positions only.
     *
     * The previous version read pickup_latitude / pickup_longitude, which are
     * not the column names — so the trip pins never appeared, and the demo
     * pins sitting behind them made that impossible to notice. It also
     * ignored the driver positions the controller was already passing in.
     */
    const tripPoints   = @json($tripPoints ?? []);
    const driverPoints = @json($driverPositions ?? []);
    const allBounds    = [];

    tripPoints.forEach(function (point) {
      L.marker([point.lat, point.lng], { icon: point.kind === 'pickup' ? pickupIcon : destinationIcon })
        .addTo(map)
        .bindPopup('<b>' + point.title + '</b><br>' + (point.note || ''));
      allBounds.push([point.lat, point.lng]);
    });

    driverPoints.forEach(function (driver) {
      L.marker([driver.lat, driver.lng], { icon: driverIcon })
        .addTo(map)
        .bindPopup('<b>Driver:</b> ' + driver.name + '<br>' +
                   (driver.code ? driver.code + '<br>' : '') +
                   (driver.is_online ? 'Online' : 'Offline') +
                   (driver.seen ? ' &middot; seen ' + driver.seen : ''));
      allBounds.push([driver.lat, driver.lng]);
    });

    if (allBounds.length > 1) {
      map.fitBounds(L.latLngBounds(allBounds), { padding: [50, 50] });
    } else if (allBounds.length === 1) {
      map.setView(allBounds[0], 13);
    } else {
      // Nothing to plot. Say so rather than leave an empty rectangle that
      // reads as a broken map.
      const note = L.control({ position: 'topright' });
      note.onAdd = function () {
        const div = L.DomUtil.create('div', 'leaflet-bar');
        div.style.cssText = 'background:#fff;padding:.5rem .75rem;font-size:.8125rem;color:#6f6b7d;border-radius:.375rem;';
        div.textContent = 'No geocoded trips or driver positions yet';
        return div;
      };
      note.addTo(map);
    }
  }
});
</script>
@endsection
