@extends('layouts/layoutMaster')

@section('title', 'System Overview')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">System Overview</h4>
      <p class="text-muted mb-0 small">Every figure here is read from live data.</p>
    </div>
    <a href="{{ route('admin.company.create') }}" class="btn btn-primary">
      <i class="ti tabler-plus me-1"></i> Register Company
    </a>
  </div>

  @if($stats['unsubscribed'] > 0)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
      <span>
        <i class="ti tabler-alert-triangle me-2"></i>
        {{ $stats['unsubscribed'] }} {{ $stats['unsubscribed'] === 1 ? 'company has' : 'companies have' }}
        no active subscription, so their panel is read-only.
      </span>
      <a href="{{ route('admin.company.list') }}" class="btn btn-sm btn-label-warning">Review</a>
    </div>
  @endif

  {{-- Headline figures --}}
  @php
    $tiles = [
      [
        'label'  => 'Organizations',
        'value'  => $stats['organisations'],
        'note'   => $stats['active_orgs'] . ' subscribed',
        'icon'   => 'tabler-building',
        'colour' => 'primary',
        'href'   => route('admin.company.list'),
      ],
      [
        'label'  => 'Monthly recurring revenue',
        'value'  => '$' . number_format($stats['mrr'], 2),
        'note'   => 'From ' . $stats['active_orgs'] . ' paying ' . ($stats['active_orgs'] === 1 ? 'company' : 'companies'),
        'icon'   => 'tabler-currency-dollar',
        'colour' => 'success',
        'href'   => route('admin.subscription'),
      ],
      [
        'label'  => 'People on the platform',
        'value'  => $stats['users'],
        'note'   => $stats['drivers'] . ' ' . ($stats['drivers'] === 1 ? 'driver' : 'drivers'),
        'icon'   => 'tabler-users',
        'colour' => 'info',
        'href'   => null,
      ],
      [
        'label'  => 'Trips this month',
        'value'  => number_format($stats['trips_month']),
        'note'   => $stats['completed_month'] . ' completed',
        'icon'   => 'tabler-route',
        'colour' => 'warning',
        'href'   => null,
        'change' => $stats['trips_change'],
      ],
    ];
  @endphp

  <div class="row g-4 mb-4">
    @foreach($tiles as $tile)
    <div class="col-12 col-sm-6 col-xl-3">
      @if($tile['href'])
        <a href="{{ $tile['href'] }}" class="card h-100 text-decoration-none">
      @else
        <div class="card h-100">
      @endif
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="min-w-0">
              <small class="text-muted d-block text-truncate">{{ $tile['label'] }}</small>
              <h4 class="fw-bold mb-0 mt-1" style="font-variant-numeric: tabular-nums;">{{ $tile['value'] }}</h4>
            </div>
            <div class="avatar flex-shrink-0">
              <span class="avatar-initial rounded bg-label-{{ $tile['colour'] }}">
                <i class="ti {{ $tile['icon'] }}"></i>
              </span>
            </div>
          </div>

          <div class="d-flex flex-wrap align-items-center gap-2">
            <small class="text-muted">{{ $tile['note'] }}</small>
            @if(($tile['change'] ?? null) !== null)
              <span class="badge {{ $tile['change'] >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                <i class="ti {{ $tile['change'] >= 0 ? 'tabler-trending-up' : 'tabler-trending-down' }} icon-xs me-1"></i>
                {{ $tile['change'] > 0 ? '+' : '' }}{{ $tile['change'] }}% vs last month
              </span>
            @endif
          </div>
        </div>
      @if($tile['href'])
        </a>
      @else
        </div>
      @endif
    </div>
    @endforeach
  </div>

  {{-- Platform revenue. MRR is what we expect to bill; this is what actually
       arrived, read from Stripe. --}}
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h5 class="mb-0">Platform revenue</h5>
        <small class="text-muted">Collected each month, from Stripe.</small>
      </div>
      <div class="d-flex flex-wrap gap-4 text-end">
        <div>
          <small class="text-muted d-block">Collected (12 mo)</small>
          <h5 class="fw-bold mb-0" style="font-variant-numeric: tabular-nums;">
            ${{ number_format($stats['revenue_total'], 2) }}
          </h5>
        </div>
        <div>
          <small class="text-muted d-block">Current MRR</small>
          <h5 class="fw-bold mb-0 text-success" style="font-variant-numeric: tabular-nums;">
            ${{ number_format($stats['mrr'], 2) }}
          </h5>
        </div>
      </div>
    </div>
    <div class="card-body">
      @if($stats['revenue_total'] <= 0)
        <div class="text-center py-5 text-muted">
          <i class="ti tabler-cash-off fs-1 d-block mb-2 text-secondary"></i>
          <h6>Nothing collected yet</h6>
          <p class="mb-0 small">
            @if($stats['active_orgs'] > 0)
              {{ $stats['active_orgs'] }} {{ $stats['active_orgs'] === 1 ? 'company is' : 'companies are' }}
              on a plan, but {{ $stats['active_orgs'] === 1 ? 'it was' : 'they were' }} assigned rather than
              bought — so no card has been charged. Revenue appears here from the first Stripe payment.
            @else
              Revenue appears here once a company pays for a plan.
            @endif
          </p>
        </div>
      @else
        <div id="revenueChart"></div>
      @endif
    </div>
  </div>

  <div class="row g-4 mb-4">
    {{-- Trip volume --}}
    <div class="col-12 col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-0">Trip volume</h5>
            <small class="text-muted">Across every company, the last twelve months.</small>
          </div>
          <span class="badge bg-label-secondary">{{ number_format($stats['trips_total']) }} all time</span>
        </div>
        <div class="card-body">
          @if($byMonth->sum('count') === 0)
            <div class="text-center py-5 text-muted">
              <i class="ti tabler-chart-bar-off fs-1 d-block mb-2 text-secondary"></i>
              <p class="mb-0 small">No trips have been booked yet.</p>
            </div>
          @else
            <div id="tripVolume"></div>
          @endif
        </div>
      </div>
    </div>

    {{-- Plan spread --}}
    <div class="col-12 col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Companies per plan</h5>
        </div>
        <div class="card-body">
          @forelse($planBreakdown as $row)
            <div class="{{ ! $loop->last ? 'mb-4' : '' }}">
              <div class="d-flex justify-content-between align-items-baseline mb-1">
                <span class="fw-semibold text-truncate">{{ $row->plan->name }}</span>
                <small class="text-muted flex-shrink-0" style="font-variant-numeric: tabular-nums;">
                  {{ $row->count }}
                </small>
              </div>
              <div class="progress" style="height: 6px;" role="progressbar"
                   aria-label="{{ $row->plan->name }}"
                   aria-valuenow="{{ $row->count }}" aria-valuemin="0"
                   aria-valuemax="{{ max(1, $stats['organisations']) }}">
                <div class="progress-bar bg-primary"
                     style="width: {{ $stats['organisations'] > 0 ? round($row->count / $stats['organisations'] * 100) : 0 }}%"></div>
              </div>
              <small class="text-muted">{{ $row->plan->priceLabel() }}</small>
            </div>
          @empty
            <p class="text-muted small mb-0">No plans have been created yet.</p>
          @endforelse

          <hr />
          <a href="{{ route('admin.subscription') }}" class="btn btn-sm btn-label-primary w-100">
            Manage plans
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Recent companies --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Recent Company Signups</h5>
      <a href="{{ route('admin.company.list') }}" class="btn btn-sm btn-label-primary">View all</a>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Company</th>
            <th class="d-none d-md-table-cell">Region</th>
            <th class="d-none d-sm-table-cell">Plan</th>
            <th>Subscription</th>
            <th class="d-none d-lg-table-cell">Joined</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($recentCompanies as $company)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-sm flex-shrink-0">
                  <img src="{{ $company->avatar_url }}" alt="{{ $company->name }}" class="rounded-circle" />
                </div>
                <div class="min-w-0">
                  <a href="{{ route('admin.company.show', $company->id) }}"
                     class="fw-semibold text-heading d-block text-truncate">{{ $company->name }}</a>
                  <small class="text-muted d-block text-truncate">{{ $company->email }}</small>
                  <small class="text-muted d-sm-none">
                    {{ $company->subscriptionPlan?->name ?? 'No plan' }}
                  </small>
                </div>
              </div>
            </td>
            <td class="d-none d-md-table-cell">
              {{-- Only what the admin actually recorded; no invented city. --}}
              <span class="text-body">{{ $company->getMeta('region') ?: '—' }}</span>
            </td>
            <td class="d-none d-sm-table-cell">
              @if($company->subscriptionPlan)
                <span class="badge bg-label-primary">{{ $company->subscriptionPlan->name }}</span>
              @else
                <span class="badge bg-label-secondary">No plan</span>
              @endif
            </td>
            <td>
              <span class="badge {{ $company->subscriptionStatusClass() }}">
                {{ $company->subscriptionStatusLabel() }}
              </span>
            </td>
            <td class="d-none d-lg-table-cell">
              <span class="text-body">{{ $company->created_at?->format('d M Y') }}</span>
              <small class="text-muted d-block">{{ $company->created_at?->diffForHumans() }}</small>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="ti tabler-building-community fs-1 d-block mb-2 text-secondary"></i>
              <h6>No companies yet</h6>
              <p class="mb-3 small">Register the first transportation company to get started.</p>
              <a href="{{ route('admin.company.create') }}" class="btn btn-primary btn-sm">Register Company</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('tripVolume');
    if (!el || typeof ApexCharts === 'undefined') return;

    const labels = @json($byMonth->pluck('label'));
    const counts = @json($byMonth->pluck('count'));

    const style   = getComputedStyle(document.documentElement);
    const muted   = style.getPropertyValue('--bs-secondary-color').trim() || '#a5a3ae';
    const grid    = style.getPropertyValue('--bs-border-color').trim() || '#dbdade';
    const primary = style.getPropertyValue('--bs-primary').trim() || '#7367f0';

    new ApexCharts(el, {
      chart: { type: 'area', height: 300, toolbar: { show: false }, parentHeightOffset: 0, fontFamily: 'inherit' },
      series: [{ name: 'Trips', data: counts }],
      colors: [primary],
      stroke: { curve: 'smooth', width: 3 },
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] }
      },
      dataLabels: { enabled: false },
      grid: { borderColor: grid, strokeDashArray: 5, padding: { top: -10, left: 4, right: 4 } },
      xaxis: {
        categories: labels,
        axisBorder: { show: false },
        axisTicks: { color: grid },
        labels: { style: { colors: muted, fontSize: '12px' } }
      },
      yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: {
          style: { colors: muted, fontSize: '12px' },
          formatter: (value) => Math.round(value)
        }
      },
      tooltip: {
        theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
        y: { formatter: (value) => value + (value === 1 ? ' trip' : ' trips') }
      },
      markers: { size: 0, hover: { size: 5 } }
    }).render();
  });

  // Revenue: a column chart, because a month's takings is a discrete figure
  // rather than something that flows between months.
  document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('revenueChart');
    if (!el || typeof ApexCharts === 'undefined') return;

    const labels  = @json($revenue->pluck('label'));
    const amounts = @json($revenue->pluck('amount'));

    const style   = getComputedStyle(document.documentElement);
    const muted   = style.getPropertyValue('--bs-secondary-color').trim() || '#a5a3ae';
    const grid    = style.getPropertyValue('--bs-border-color').trim() || '#dbdade';
    const success = style.getPropertyValue('--bs-success').trim() || '#28c76f';

    const money = (value) => '$' + Number(value).toLocaleString(undefined, {
      minimumFractionDigits: 0, maximumFractionDigits: 0
    });

    new ApexCharts(el, {
      chart: { type: 'bar', height: 280, toolbar: { show: false }, parentHeightOffset: 0, fontFamily: 'inherit' },
      series: [{ name: 'Collected', data: amounts }],
      colors: [success],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
      dataLabels: { enabled: false },
      grid: { borderColor: grid, strokeDashArray: 5, padding: { top: -10, left: 4, right: 4 } },
      xaxis: {
        categories: labels,
        axisBorder: { show: false },
        axisTicks: { color: grid },
        labels: { style: { colors: muted, fontSize: '12px' } }
      },
      yaxis: {
        min: 0,
        forceNiceScale: true,
        labels: { style: { colors: muted, fontSize: '12px' }, formatter: money }
      },
      tooltip: {
        theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
        y: { formatter: (value) => '$' + Number(value).toFixed(2) }
      }
    }).render();
  });
</script>
@endsection
