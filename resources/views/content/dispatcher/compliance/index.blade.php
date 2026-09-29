@extends('layouts/layoutMaster')

@section('title', 'Compliance Center')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Compliance Center</h4>
      <p class="text-muted mb-0 small">
        Licences, medical cards and insurance across the roster.
        Anything inside {{ \App\Models\DriverDocument::EXPIRY_WARNING_DAYS }} days counts as expiring.
      </p>
    </div>
  </div>

  @if($counts['expired'] > 0)
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
      <i class="ti tabler-alert-triangle fs-5 flex-shrink-0"></i>
      <div>
        <strong>{{ $counts['expired'] }} {{ $counts['expired'] === 1 ? 'driver has' : 'drivers have' }} an expired document.</strong>
        <span class="d-block d-sm-inline">They should not be dispatched until it is renewed.</span>
      </div>
    </div>
  @endif

  {{-- Counts, which double as filters --}}
  <div class="row g-3 mb-4">
    @php
      $tiles = [
        ['label' => 'Expired',     'value' => $counts['expired'],  'colour' => 'danger',    'icon' => 'tabler-alert-triangle', 'state' => 'expired'],
        ['label' => 'Expiring',    'value' => $counts['expiring'], 'colour' => 'warning',   'icon' => 'tabler-clock-exclamation', 'state' => 'expiring'],
        ['label' => 'No documents','value' => $counts['missing'],  'colour' => 'secondary', 'icon' => 'tabler-file-off', 'state' => 'missing'],
        ['label' => 'Up to date',  'value' => $counts['valid'],    'colour' => 'success',   'icon' => 'tabler-circle-check', 'state' => 'valid'],
      ];

      // Badge colour and wording per compliance state, declared here so the
      // table below can stay on the inline @php form.
      $stateBadges = [
        'expired'  => ['bg-label-danger',    'Expired'],
        'expiring' => ['bg-label-warning',   'Expiring'],
        'missing'  => ['bg-label-secondary', 'No documents'],
        'valid'    => ['bg-label-success',   'Up to date'],
      ];
    @endphp
    @foreach($tiles as $tile)
    <div class="col-6 col-lg-3">
      <a href="{{ route('dispatcher.compliance', ['state' => $tile['state']]) }}"
         class="card h-100 text-decoration-none {{ request('state') === $tile['state'] ? 'border-' . $tile['colour'] : '' }}">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-{{ $tile['colour'] }}">
              <i class="ti {{ $tile['icon'] }}"></i>
            </span>
          </div>
          <div class="min-w-0">
            <h4 class="fw-bold mb-0">{{ $tile['value'] }}</h4>
            <small class="text-muted text-truncate d-block">{{ $tile['label'] }}</small>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>

  @if(request('state'))
    <div class="mb-3">
      <a href="{{ route('dispatcher.compliance') }}" class="text-muted small">
        <i class="ti tabler-x icon-xs me-1"></i>Show all drivers
      </a>
    </div>
  @endif

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Driver</th>
            <th class="d-none d-md-table-cell">Documents</th>
            <th class="d-none d-lg-table-cell">Next expiry</th>
            <th>State</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($rows as $row)
          @php($driver = $row->driver)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-sm flex-shrink-0">
                  <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
                </div>
                <div class="min-w-0">
                  <a href="{{ route('dispatcher.compliance.driver', $driver->id) }}"
                     class="fw-semibold text-heading d-block text-truncate">{{ $driver->name }}</a>
                  <small class="text-muted d-block">{{ $driver->driver_code }}</small>
                  <small class="text-muted d-md-none d-block">
                    {{ $row->documents->count() }} {{ $row->documents->count() === 1 ? 'document' : 'documents' }}
                  </small>
                </div>
              </div>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="badge bg-label-secondary">{{ $row->documents->count() }} on file</span>
              @if($row->expired > 0)
                <span class="badge bg-label-danger ms-1">{{ $row->expired }} expired</span>
              @endif
              @if($row->expiring > 0)
                <span class="badge bg-label-warning ms-1">{{ $row->expiring }} expiring</span>
              @endif
            </td>
            <td class="d-none d-lg-table-cell">
              @if($row->soonest)
                <span class="text-body">{{ $row->soonest->expires_on->format('d M Y') }}</span>
                <small class="text-muted d-block">{{ $row->soonest->typeLabel() }}</small>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              @php($stateBadge = $stateBadges[$row->state] ?? $stateBadges['valid'])
              <span class="badge {{ $stateBadge[0] }}">{{ $stateBadge[1] }}</span>
            </td>
            <td class="text-end">
              <a href="{{ route('dispatcher.compliance.driver', $driver->id) }}"
                 class="btn btn-sm btn-label-primary">
                <i class="ti tabler-files"></i>
                <span class="d-none d-lg-inline ms-1">Documents</span>
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="ti tabler-shield-check fs-1 d-block mb-2 text-secondary"></i>
              <h6>Nothing here</h6>
              <p class="mb-0 small">No drivers match this view.</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($vehiclesInMaintenance > 0)
    <div class="alert alert-warning mt-4 d-flex align-items-center gap-2" role="alert">
      <i class="ti tabler-tool flex-shrink-0"></i>
      <span>
        {{ $vehiclesInMaintenance }} {{ $vehiclesInMaintenance === 1 ? 'vehicle is' : 'vehicles are' }} in maintenance.
        <a href="{{ route('dispatcher.fleet.index') }}" class="alert-link">Open Fleet Management</a>
      </span>
    </div>
  @endif
</div>
@endsection
