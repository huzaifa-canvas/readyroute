@extends('layouts/layoutMaster')

@section('title', 'Reports')

@section('vendor-style')
{{-- The theme ships this as SCSS, so it comes through Vite rather than a
     public/assets link. --}}
@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php
    $peak = max(1, $byDay->max('count'));
    $query = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Reports</h4>
      <p class="text-muted mb-0 small">
        {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}
      </p>
    </div>
    <a href="{{ route('dispatcher.reports.export', $query) }}" class="btn btn-label-primary">
      <i class="ti tabler-download me-1"></i>Export CSV
    </a>
  </div>

  {{-- Date range --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('dispatcher.reports') }}" class="row g-3 align-items-end">
        <div class="col-6 col-lg-3">
          <label class="form-label" for="from">From</label>
          <input type="date" id="from" name="from" class="form-control" value="{{ $from->toDateString() }}" />
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label" for="to">To</label>
          <input type="date" id="to" name="to" class="form-control" value="{{ $to->toDateString() }}" />
        </div>
        <div class="col-12 col-lg-3">
          <button type="submit" class="btn btn-primary w-100">Apply</button>
        </div>
        <div class="col-12 col-lg-3 d-flex gap-2">
          <a href="{{ route('dispatcher.reports', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}"
             class="btn btn-label-secondary flex-grow-1 btn-sm">This month</a>
          <a href="{{ route('dispatcher.reports', ['from' => now()->subDays(29)->toDateString(), 'to' => now()->toDateString()]) }}"
             class="btn btn-label-secondary flex-grow-1 btn-sm">Last 30 days</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Headline numbers --}}
  <div class="row g-4 mb-4">
    @php
      $tiles = [
        ['Trips',        $summary['total'],       'tabler-route',         'primary'],
        ['Completed',    $summary['completed'],   'tabler-circle-check',  'success'],
        ['Cancelled',    $summary['cancelled'],   'tabler-circle-x',      'danger'],
        ['In progress',  $summary['in_progress'], 'tabler-progress',      'info'],
        ['Completion',   $summary['completion_rate'] . '%', 'tabler-percentage', 'primary'],
        ['On time',      $summary['on_time_rate'] === null ? '—' : $summary['on_time_rate'] . '%', 'tabler-clock-check', 'success'],
      ];
    @endphp
    @foreach($tiles as [$label, $value, $icon, $colour])
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body text-center p-3">
          <div class="avatar avatar-sm mx-auto mb-2">
            <span class="avatar-initial rounded bg-label-{{ $colour }}"><i class="ti {{ $icon }}"></i></span>
          </div>
          <h4 class="fw-bold mb-0">{{ $value }}</h4>
          <small class="text-muted">{{ $label }}</small>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  @if($summary['on_time_rate'] !== null && $summary['scored'] < $summary['completed'])
    <p class="text-muted small mb-4">
      <i class="ti tabler-info-circle icon-xs me-1"></i>
      Punctuality is measured on {{ $summary['scored'] }} of {{ $summary['completed'] }} completed trips —
      the rest had no scheduled pickup time to score against.
    </p>
  @endif

  <div class="row g-4 mb-4">
    {{-- Daily volume --}}
    <div class="col-12 col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Trips per day</h5>
          <small class="text-muted">Peak {{ $peak }}</small>
        </div>
        <div class="card-body">
          @if($byDay->count() > 92)
            <p class="text-muted small mb-0">
              That range is too wide to chart by day. Narrow it to three months or less.
            </p>
          @elseif($summary['total'] === 0)
            <p class="text-muted small mb-0">No trips in this range.</p>
          @else
            <div id="tripsPerDay"></div>
          @endif
        </div>
      </div>
    </div>

    {{-- Status mix --}}
    <div class="col-12 col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Status mix</h5>
        </div>
        <div class="card-body">
          @forelse($byStatus as $row)
            <div class="{{ ! $loop->last ? 'mb-3' : '' }}">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="badge {{ $row['class'] }}">{{ $row['label'] }}</span>
                <small class="fw-semibold">{{ $row['count'] }}</small>
              </div>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary"
                     style="width: {{ $summary['total'] > 0 ? round($row['count'] / $summary['total'] * 100) : 0 }}%"></div>
              </div>
            </div>
          @empty
            <p class="text-muted small mb-0">No trips in this range.</p>
          @endforelse

          @if($unassigned > 0)
            <hr />
            <p class="mb-0 small text-warning">
              <i class="ti tabler-alert-circle icon-xs me-1"></i>
              {{ $unassigned }} {{ $unassigned === 1 ? 'trip has' : 'trips have' }} no driver assigned.
            </p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    {{-- Driver performance --}}
    <div class="col-12 col-xl-7">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Driver performance</h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Driver</th>
                <th class="text-center">Assigned</th>
                <th class="text-center">Done</th>
                <th class="d-none d-sm-table-cell text-center">Miles</th>
                <th class="text-center">On time</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @forelse($driverRows as $row)
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar avatar-xs flex-shrink-0">
                      <img src="{{ $row->driver->avatar_url }}" alt="" class="rounded-circle" />
                    </div>
                    <div class="min-w-0">
                      <span class="fw-semibold d-block text-truncate">{{ $row->driver->name }}</span>
                      <small class="text-muted">{{ $row->driver->driver_code }}</small>
                    </div>
                  </div>
                </td>
                <td class="text-center">{{ $row->assigned }}</td>
                <td class="text-center">{{ $row->completed }}</td>
                <td class="d-none d-sm-table-cell text-center" style="font-variant-numeric: tabular-nums;">
                  {{ $row->miles ?: '—' }}
                </td>
                <td class="text-center">
                  @if($row->on_time_rate === null)
                    <span class="text-muted">—</span>
                  @else
                    <span class="badge {{ $row->on_time_rate >= 90 ? 'bg-label-success' : ($row->on_time_rate >= 70 ? 'bg-label-warning' : 'bg-label-danger') }}">
                      {{ $row->on_time_rate }}%
                    </span>
                  @endif
                </td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-center py-4 text-muted">No drivers.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Vehicles and clients --}}
    <div class="col-12 col-xl-5">
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Vehicle utilisation</h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Vehicle</th>
                <th class="text-center">Trips</th>
                <th class="text-center">Miles</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @forelse($vehicleRows as $row)
              <tr>
                <td>
                  <span class="fw-semibold d-block text-truncate">{{ $row->vehicle->name }}</span>
                  <small class="text-muted">{{ $row->vehicle->number_plate ?: '—' }}</small>
                </td>
                <td class="text-center">{{ $row->trips }}</td>
                <td class="text-center" style="font-variant-numeric: tabular-nums;">{{ $row->miles ?: '—' }}</td>
              </tr>
              @empty
              <tr><td colspan="3" class="text-center py-4 text-muted">No vehicles.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Most frequent passengers</h5>
        </div>
        <div class="list-group list-group-flush">
          @forelse($topClients as $row)
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <span class="text-truncate">{{ $row->name }}</span>
              <span class="badge bg-label-primary flex-shrink-0">{{ $row->count }}</span>
            </div>
          @empty
            <div class="list-group-item text-center py-4 text-muted">No trips in this range.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  (function () {
    var el = document.getElementById('tripsPerDay');
    if (!el || typeof ApexCharts === 'undefined') return;

    // One point per day, dates as real timestamps so the axis can label and
    // space them properly instead of leaving bare bars.
    var series = @json($byDay->map(fn ($d) => [
        $d['date']->copy()->startOfDay()->valueOf(),
        $d['count'],
    ])->values());

    var style   = getComputedStyle(document.documentElement);
    var ink     = style.getPropertyValue('--bs-body-color').trim() || '#5d596c';
    var muted   = style.getPropertyValue('--bs-secondary-color').trim() || '#a5a3ae';
    var grid    = style.getPropertyValue('--bs-border-color').trim() || '#dbdade';
    var primary = style.getPropertyValue('--bs-primary').trim() || '#7367f0';

    var chart = new ApexCharts(el, {
      chart: {
        type: 'bar',
        height: 260,
        parentHeightOffset: 0,
        toolbar: { show: false },
        fontFamily: 'inherit'
      },
      series: [{ name: 'Trips', data: series }],
      colors: [primary],
      plotOptions: {
        bar: { borderRadius: 4, columnWidth: series.length > 40 ? '70%' : '45%' }
      },
      dataLabels: { enabled: false },
      grid: {
        borderColor: grid,
        strokeDashArray: 5,
        padding: { top: -10, left: 4, right: 4 },
        xaxis: { lines: { show: false } }
      },
      xaxis: {
        type: 'datetime',
        axisBorder: { show: false },
        axisTicks: { color: grid },
        labels: {
          style: { colors: muted, fontSize: '12px' },
          // Short, readable ticks; Apex thins them out on narrow screens.
          datetimeFormatter: { day: 'dd MMM', month: 'MMM' },
          rotate: 0,
          hideOverlappingLabels: true
        },
        tooltip: { enabled: false }
      },
      yaxis: {
        // Trips are whole numbers, so half-trip gridlines are noise.
        min: 0,
        forceNiceScale: true,
        labels: {
          style: { colors: muted, fontSize: '12px' },
          formatter: function (value) { return Math.round(value); }
        }
      },
      tooltip: {
        theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
        x: { format: 'ddd dd MMM yyyy' },
        y: {
          formatter: function (value) { return value + (value === 1 ? ' trip' : ' trips'); },
          title: { formatter: function () { return ''; } }
        }
      },
      states: { hover: { filter: { type: 'darken', value: 0.9 } } },
      responsive: [{
        breakpoint: 576,
        options: {
          chart: { height: 220 },
          plotOptions: { bar: { columnWidth: '80%' } }
        }
      }]
    });

    chart.render();
  })();
</script>
@endsection
