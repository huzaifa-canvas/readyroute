@extends('layouts/layoutMaster')

@section('title', 'Incidents & Alerts')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  {{-- An open SOS outranks everything else on this screen. --}}
  @if($counts['sos_open'] > 0)
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
      <i class="ti tabler-urgent fs-4 flex-shrink-0"></i>
      <div>
        <strong>{{ $counts['sos_open'] }} emergency {{ $counts['sos_open'] === 1 ? 'alert is' : 'alerts are' }} open.</strong>
        <span class="d-block d-sm-inline">Acknowledge so the driver knows someone is looking.</span>
      </div>
    </div>
  @endif

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Incidents &amp; Alerts</h4>
      <p class="text-muted mb-0 small">What drivers have reported from the road.</p>
    </div>
  </div>

  {{-- Counts double as status filters --}}
  <div class="row g-3 mb-4">
    @php
      $tiles = [
        ['label' => 'Open',         'value' => $counts['open'],         'colour' => 'danger',  'icon' => 'tabler-alert-circle', 'status' => 'open'],
        ['label' => 'Acknowledged', 'value' => $counts['acknowledged'], 'colour' => 'info',    'icon' => 'tabler-eye-check',    'status' => 'acknowledged'],
        ['label' => 'Resolved',     'value' => $counts['resolved'],     'colour' => 'success', 'icon' => 'tabler-circle-check', 'status' => 'resolved'],
      ];
    @endphp
    @foreach($tiles as $tile)
    <div class="col-12 col-sm-4">
      <a href="{{ route('dispatcher.incidents.index', ['status' => $tile['status']]) }}"
         class="card h-100 text-decoration-none {{ request('status') === $tile['status'] ? 'border-' . $tile['colour'] : '' }}">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-{{ $tile['colour'] }}">
              <i class="ti {{ $tile['icon'] }}"></i>
            </span>
          </div>
          <div>
            <h4 class="fw-bold mb-0">{{ $tile['value'] }}</h4>
            <small class="text-muted">{{ $tile['label'] }}</small>
          </div>
        </div>
      </a>
    </div>
    @endforeach
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('dispatcher.incidents.index') }}" class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="type">Type</label>
          <select id="type" name="type" class="form-select">
            <option value="">All types</option>
            @foreach(\App\Models\TripIncident::TYPES as $key => $label)
              <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="driver">Driver</label>
          <select id="driver" name="driver" class="form-select">
            <option value="">All drivers</option>
            @foreach($drivers as $driver)
              <option value="{{ $driver->id }}" @selected((int) request('driver') === $driver->id)>{{ $driver->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="status">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Open &amp; acknowledged</option>
            @foreach(\App\Models\TripIncident::STATUSES as $key => $label)
              <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2">
          <button type="submit" class="btn btn-label-primary flex-grow-1">Filter</button>
          @if(request()->hasAny(['type', 'driver', 'status']))
            <a href="{{ route('dispatcher.incidents.index') }}" class="btn btn-label-secondary">Clear</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Incident</th>
            <th class="d-none d-md-table-cell">Driver</th>
            <th class="d-none d-lg-table-cell">Trip</th>
            <th class="d-none d-sm-table-cell">Severity</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($incidents as $incident)
          <tr class="{{ $incident->isSos() && ! $incident->isResolved() ? 'table-danger' : '' }}">
            <td>
              <div class="d-flex align-items-start gap-2">
                @if($incident->isSos())
                  <i class="ti tabler-urgent text-danger fs-5 flex-shrink-0 mt-1"></i>
                @endif
                <div class="min-w-0">
                  <a href="{{ route('dispatcher.incidents.show', $incident->id) }}"
                     class="fw-semibold text-heading d-block text-truncate">{{ $incident->headline() }}</a>
                  <small class="text-muted d-block">
                    {{ $incident->typeLabel() }} &middot; {{ $incident->created_at->diffForHumans() }}
                  </small>
                  <small class="text-muted d-md-none d-block">
                    {{ $incident->driver?->name }}
                    <span class="badge {{ $incident->severityClass() }} ms-1">{{ $incident->severityLabel() }}</span>
                  </small>
                </div>
              </div>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="text-body">{{ $incident->driver?->name ?? '—' }}</span>
              <small class="text-muted d-block">{{ $incident->driver?->driver_code }}</small>
            </td>
            <td class="d-none d-lg-table-cell">
              @if($incident->trip)
                <a href="{{ route('dispatcher.trip.details', $incident->trip->id) }}" class="text-body">
                  {{ $incident->trip->reference() }}
                </a>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="d-none d-sm-table-cell">
              <span class="badge {{ $incident->severityClass() }}">{{ $incident->severityLabel() }}</span>
            </td>
            <td>
              <span class="badge {{ $incident->statusClass() }}">{{ $incident->statusLabel() }}</span>
            </td>
            <td class="text-end">
              <div class="d-flex gap-2 justify-content-end">
                @if($incident->status === 'open')
                  <form method="POST" action="{{ route('dispatcher.incidents.acknowledge', $incident->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-label-info" title="Acknowledge">
                      <i class="ti tabler-eye-check"></i>
                      <span class="d-none d-xl-inline ms-1">Ack</span>
                    </button>
                  </form>
                @endif
                <a href="{{ route('dispatcher.incidents.show', $incident->id) }}"
                   class="btn btn-sm btn-label-primary" aria-label="Open incident">
                  <i class="ti tabler-eye"></i>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="ti tabler-shield-check fs-1 d-block mb-2 text-success"></i>
              <h6>Nothing to deal with</h6>
              <p class="mb-0 small">No incidents match this view.</p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($incidents->hasPages())
    <div class="card-footer d-flex justify-content-center py-3 border-top">
      {{ $incidents->links() }}
    </div>
    @endif
  </div>
</div>
@endsection
