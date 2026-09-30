{{--
  The passenger's tracking page.

  Standalone rather than inside the panel layout: whoever opens this has no
  account, so there is no menu, no navbar and nothing that assumes a session.
  It is built phone-first because it arrives as an SMS link.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  {{-- A tracking link should never be indexed or passed on in a referrer. --}}
  <meta name="robots" content="noindex, nofollow" />
  <meta name="referrer" content="no-referrer" />

  <title>{{ $state['headline'] }} — {{ $company?->name ?? config('app.name') }}</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <style>
    :root {
      --ink:      #2f2b3d;
      --muted:    #6f6b7d;
      --line:     #dbdade;
      --surface:  #ffffff;
      --ground:   #f8f7fa;
      --accent:   #7367f0;
      --good:     #28c76f;
      --warn:     #ff9f43;
      --radius:   .625rem;
    }

    @media (prefers-color-scheme: dark) {
      :root:not([data-theme="light"]) {
        --ink:     #e4e6e8;
        --muted:   #a5a3ae;
        --line:    #3b3a51;
        --surface: #2f3349;
        --ground:  #25293c;
      }
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: var(--ground);
      color: var(--ink);
      font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 15px;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    .wrap { max-width: 34rem; margin: 0 auto; padding: 16px; }

    .brand {
      display: flex; align-items: center; gap: .5rem;
      padding: .5rem 0 1rem; color: var(--muted); font-size: .8125rem;
    }
    .brand strong { color: var(--ink); font-size: .9375rem; }

    .card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 16px;
    }
    .card-body { padding: 1.25rem; }

    .status-line { display: flex; align-items: center; gap: .5rem; margin-bottom: .25rem; }
    .dot {
      width: .6rem; height: .6rem; border-radius: 50%;
      background: var(--good); flex-shrink: 0;
    }
    .dot.live { animation: pulse 1.8s ease-in-out infinite; }
    @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .35 } }
    @media (prefers-reduced-motion: reduce) { .dot.live { animation: none } }

    h1 { font-size: 1.375rem; font-weight: 600; margin: 0 0 .25rem; text-wrap: balance; }
    .eta { font-size: 2.25rem; font-weight: 700; margin: .5rem 0 0; font-variant-numeric: tabular-nums; }
    .eta small { font-size: .9375rem; font-weight: 500; color: var(--muted); }

    #map { height: 260px; width: 100%; background: var(--ground); }
    @media (min-width: 576px) { #map { height: 320px; } }

    .rows { display: grid; gap: .875rem; }
    .row { display: flex; gap: .75rem; align-items: flex-start; }
    .row .label { color: var(--muted); font-size: .8125rem; min-width: 5.5rem; flex-shrink: 0; }
    .row .value { font-weight: 500; word-break: break-word; }

    .avatar {
      width: 2.75rem; height: 2.75rem; border-radius: 50%;
      background: var(--accent); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-weight: 600; font-size: 1.125rem; flex-shrink: 0;
    }

    .btn {
      display: flex; align-items: center; justify-content: center; gap: .5rem;
      width: 100%; padding: .75rem 1rem;
      background: var(--accent); color: #fff;
      border: 0; border-radius: var(--radius);
      font: inherit; font-weight: 600; text-decoration: none;
      cursor: pointer;
    }
    .btn:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

    .note { color: var(--muted); font-size: .8125rem; text-align: center; padding: .5rem 0 1.5rem; }

    .badge {
      display: inline-block; padding: .25rem .625rem; border-radius: 999px;
      font-size: .75rem; font-weight: 600;
      background: color-mix(in srgb, var(--accent) 16%, transparent);
      color: var(--accent);
    }
    .badge.done { background: color-mix(in srgb, var(--good) 16%, transparent); color: var(--good); }
  </style>
</head>
<body>
  <div class="wrap">

    <div class="brand">
      <span aria-hidden="true">🚐</span>
      <strong>{{ $company?->name ?? config('app.name') }}</strong>
      <span>&middot; {{ $state['reference'] }}</span>
    </div>

    {{-- Status --}}
    <div class="card">
      <div class="card-body">
        <div class="status-line">
          <span class="dot {{ $state['is_finished'] ? '' : 'live' }}"
                style="{{ $state['is_finished'] ? 'background: var(--muted)' : '' }}"></span>
          <span class="badge {{ $state['is_finished'] ? 'done' : '' }}" id="statusBadge">
            {{ $state['status_label'] }}
          </span>
        </div>

        <h1 id="headline">{{ $state['headline'] }}</h1>

        <p class="eta" id="etaBox" @if($state['eta_minutes'] === null) style="display:none" @endif>
          <span id="etaValue">{{ $state['eta_minutes'] }}</span><small> min away</small>
        </p>

        @if($state['is_finished'])
          <p style="color: var(--muted); margin: .5rem 0 0;">
            This link is no longer tracking a live vehicle.
          </p>
        @endif
      </div>

      @if($state['map']['has_points'])
        <div id="map" role="img" aria-label="Map of the trip"></div>
      @elseif(! $state['is_finished'])
        {{-- Nothing has been geocoded, so there is no honest map to draw.
             Saying so beats an empty grey box. --}}
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .875rem;">
          A map will appear here once your driver is on the way.
        </div>
      @endif
    </div>

    {{-- Driver and vehicle --}}
    @if($state['driver'] || $state['vehicle'])
    <div class="card">
      <div class="card-body">
        <div class="rows">
          @if($state['driver'])
            <div class="row" style="align-items: center;">
              <div class="avatar" aria-hidden="true">{{ $state['driver']['initial'] }}</div>
              <div>
                <div class="value">{{ $state['driver']['name'] }}</div>
                <div class="label" style="min-width:0">Your driver</div>
              </div>
            </div>
          @endif

          @if($state['vehicle'])
            <div class="row">
              <span class="label">Vehicle</span>
              <span class="value">
                {{ $state['vehicle']['model'] ?: $state['vehicle']['name'] }}
                @if($state['vehicle']['plate'])
                  &middot; {{ $state['vehicle']['plate'] }}
                @endif
              </span>
            </div>
          @endif

          <div class="row">
            <span class="label">Pickup</span>
            <span class="value">{{ $state['pickup']['address'] }}</span>
          </div>

          @if($state['pickup']['time'])
            <div class="row">
              <span class="label">Scheduled</span>
              <span class="value">{{ \Illuminate\Support\Carbon::parse($state['pickup']['time'])->format('g:i A') }}</span>
            </div>
          @endif
        </div>
      </div>
    </div>
    @endif

    {{-- Contact. The dispatch number, not the driver's own. --}}
    @if($state['contact'])
      <a class="btn" href="tel:{{ $state['contact'] }}">
        <span aria-hidden="true">📞</span> Call dispatch
      </a>
    @endif

    <p class="note">
      This page updates on its own.
      <span id="updatedAt"></span>
    </p>
  </div>

  @if($state['map']['has_points'])
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  @endif

  @php($tiles = \App\Support\MapTiles::current())
  <script>
    (function () {
      var pollUrl  = @json(route('track.position', $trip->public_token));
      var finished = @json($state['is_finished']);
      var start    = @json($state['position']);
      var pickup   = @json($state['pickup']);

      var mapPoints = @json($state['map']);
      var map = null, vehicleMarker = null;

      function initMap() {
        if (!window.L || !document.getElementById('map') || !mapPoints.has_points) return;

        map = L.map('map', { zoomControl: false, attributionControl: false });
        // Same basemap as the panel, from the same config.
        L.tileLayer(@json($tiles['url']), {
          maxZoom: {{ $tiles['max_zoom'] }},
          attribution: @json($tiles['attribution'])
        }).addTo(map);

        var bounds = [];

        // The pickup is pinned as soon as the trip exists, so the page has
        // something to show before the driver sets off.
        if (mapPoints.pickup) {
          L.circleMarker([mapPoints.pickup.lat, mapPoints.pickup.lng], {
            radius: 9, color: '#7367f0', fillColor: '#7367f0', fillOpacity: .9, weight: 3
          }).addTo(map).bindTooltip('Pickup', { permanent: false });
          bounds.push([mapPoints.pickup.lat, mapPoints.pickup.lng]);
        }

        if (mapPoints.dropoff) {
          L.circleMarker([mapPoints.dropoff.lat, mapPoints.dropoff.lng], {
            radius: 8, color: '#28c76f', fillColor: '#28c76f', fillOpacity: .9, weight: 3
          }).addTo(map).bindTooltip('Drop-off', { permanent: false });
          bounds.push([mapPoints.dropoff.lat, mapPoints.dropoff.lng]);
        }

        if (start) {
          vehicleMarker = L.marker([start.lat, start.lng]).addTo(map).bindTooltip('Your ride');
          bounds.push([start.lat, start.lng]);
        }

        if (bounds.length > 1) {
          map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40] });
        } else if (bounds.length === 1) {
          map.setView(bounds[0], 14);
        }
      }

      /**
       * The vehicle appears mid-trip without a page reload, so the marker is
       * created on the first position rather than only at startup.
       */
      function placeVehicle(position) {
        if (!map || !position) return;

        if (vehicleMarker) {
          vehicleMarker.setLatLng([position.lat, position.lng]);
        } else {
          vehicleMarker = L.marker([position.lat, position.lng]).addTo(map).bindTooltip('Your ride');
          var all = [[position.lat, position.lng]];
          if (mapPoints.pickup) all.push([mapPoints.pickup.lat, mapPoints.pickup.lng]);
          map.fitBounds(L.latLngBounds(all), { padding: [40, 40] });
        }
      }

      function stamp() {
        var el = document.getElementById('updatedAt');
        if (el) {
          el.textContent = 'Last checked ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
      }

      async function refresh() {
        try {
          var response = await fetch(pollUrl, { headers: { 'Accept': 'application/json' } });
          if (!response.ok) return;

          var state = (await response.json()).data;

          document.getElementById('headline').textContent    = state.headline;
          document.getElementById('statusBadge').textContent = state.status_label;

          var etaBox = document.getElementById('etaBox');
          if (state.eta_minutes !== null && state.eta_minutes !== undefined) {
            document.getElementById('etaValue').textContent = state.eta_minutes;
            etaBox.style.display = '';
          } else {
            etaBox.style.display = 'none';
          }

          placeVehicle(state.position);

          stamp();

          // Once the trip is over there is nothing left to poll for.
          if (state.is_finished) {
            clearInterval(timer);
            document.querySelectorAll('.dot').forEach(function (d) {
              d.classList.remove('live');
              d.style.background = 'var(--muted)';
            });
          }
        } catch (error) {
          // Offline or a dropped request; the next tick will catch up.
        }
      }

      initMap();
      stamp();

      var timer = finished ? null : setInterval(refresh, 15000);
    })();
  </script>
</body>
</html>
