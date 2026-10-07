@extends('layouts/layoutMaster')

@section('title', $driver->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php
    $online   = $driver->isCurrentlyOnline();
    $expired  = $documents->filter->isExpired()->count();
    $expiring = $documents->filter->isExpiring()->count();
  @endphp

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('dispatcher.driver.list') }}">Drivers</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $driver->name }}</li>
    </ol>
  </nav>

  @if($expired > 0)
    <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
      <span>
        <i class="ti tabler-alert-triangle me-2"></i>
        {{ $expired }} {{ $expired === 1 ? 'document has' : 'documents have' }} expired.
      </span>
      <a href="{{ route('dispatcher.compliance.driver', $driver->id) }}" class="btn btn-sm btn-label-danger">
        Manage documents
      </a>
    </div>
  @endif

  {{-- Header --}}
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
        <div class="avatar avatar-xl flex-shrink-0 mx-auto mx-md-0 position-relative">
          <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
          <span class="badge badge-dot position-absolute bottom-0 end-0 border border-2 border-white rounded-circle {{ $online ? 'bg-success' : 'bg-secondary' }}"
                style="width:1rem;height:1rem;" title="{{ $online ? 'Online' : 'Offline' }}"></span>
        </div>

        <div class="flex-grow-1 text-center text-md-start min-w-0">
          <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
            <h4 class="fw-bold text-heading mb-0 text-break">{{ $driver->name }}</h4>
            <span class="badge bg-label-secondary">{{ $driver->driver_code }}</span>
            <span class="badge {{ $online ? 'bg-label-success' : 'bg-label-secondary' }}">
              {{ $online ? 'Online' : 'Offline' }}
            </span>
          </div>
          <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3 text-muted small">
            <span class="text-break"><i class="ti tabler-mail icon-xs me-1"></i>{{ $driver->email }}</span>
            @if($driver->phone_number)
              <a href="tel:{{ $driver->phone_number }}" class="text-muted text-decoration-none">
                <i class="ti tabler-phone icon-xs me-1"></i>{{ $driver->phone_number }}
              </a>
            @endif
            @if($driver->assignedVehicle)
              <span><i class="ti tabler-car icon-xs me-1"></i>{{ $driver->assignedVehicle->name }}</span>
            @endif
            @if($driver->last_seen_at)
              <span><i class="ti tabler-clock icon-xs me-1"></i>Seen {{ $driver->last_seen_at->diffForHumans() }}</span>
            @endif
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-end">
          <a href="{{ route('dispatcher.messages.thread', $driver->id) }}" class="btn btn-primary">
            <i class="ti tabler-message-circle me-1"></i>Message
          </a>
          <a href="{{ route('dispatcher.driver.edit', $driver->id) }}" class="btn btn-label-primary">
            <i class="ti tabler-edit me-1"></i>Edit
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Stats --}}
  <div class="row g-4 mb-4">
    @php
      $tiles = [
        ['Trips today',   $stats['today'],       'tabler-calendar-event', 'primary'],
        ['Completed',     $stats['completed'],   'tabler-circle-check',   'success'],
        ['Total trips',   $stats['total_trips'], 'tabler-route',          'info'],
        ['Miles driven',  $stats['miles'],       'tabler-road',           'secondary'],
        ['On time',       $stats['on_time_rate'] === null ? '—' : $stats['on_time_rate'] . '%', 'tabler-clock-check', 'success'],
        ['Open incidents', $stats['open_incidents'], 'tabler-urgent',     $stats['open_incidents'] > 0 ? 'danger' : 'secondary'],
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

  <div class="row g-4">

    {{-- Current position & active trip --}}
    <div class="col-12 col-lg-5">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Position</h5>
          @if($driver->last_location_at)
            <small class="text-muted">{{ $driver->last_location_at->diffForHumans() }}</small>
          @endif
        </div>
        <div class="card-body">
          @if($driver->last_lat && $driver->last_lng)
            <p class="mb-2" style="font-variant-numeric: tabular-nums;">
              <i class="ti tabler-map-pin icon-xs me-1 text-primary"></i>
              {{ number_format((float) $driver->last_lat, 5) }}, {{ number_format((float) $driver->last_lng, 5) }}
            </p>
            <a class="btn btn-sm btn-label-primary mb-3"
               href="https://www.google.com/maps/search/?api=1&query={{ $driver->last_lat }},{{ $driver->last_lng }}"
               target="_blank" rel="noopener">
              <i class="ti tabler-external-link me-1"></i>Open in Maps
            </a>

            @if($trail->isNotEmpty())
              <h6 class="mb-2 small text-muted text-uppercase">Recent pings</h6>
              <div class="d-flex flex-column gap-1">
                @foreach($trail as $point)
                  <small class="text-muted d-flex justify-content-between">
                    <span style="font-variant-numeric: tabular-nums;">
                      {{ number_format((float) $point->lat, 4) }}, {{ number_format((float) $point->lng, 4) }}
                    </span>
                    <span>{{ $point->created_at->format('h:i A') }}</span>
                  </small>
                @endforeach
              </div>
            @endif
          @else
            <div class="text-center py-4 text-muted">
              <i class="ti tabler-map-off fs-2 d-block mb-2 text-secondary"></i>
              <p class="mb-0 small">No position reported yet.</p>
            </div>
          @endif
        </div>
      </div>

      {{-- Documents --}}
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Documents</h5>
          <a href="{{ route('dispatcher.compliance.driver', $driver->id) }}" class="btn btn-sm btn-label-primary">
            Manage
          </a>
        </div>
        <div class="card-body">
          @forelse($documents as $document)
            <div class="d-flex justify-content-between align-items-center {{ ! $loop->last ? 'mb-3' : '' }}">
              <div class="min-w-0">
                <span class="d-block text-truncate">{{ $document->typeLabel() }}</span>
                <small class="text-muted">
                  {{ $document->expires_on ? 'Expires ' . $document->expires_on->format('d M Y') : 'No expiry' }}
                </small>
              </div>
              <span class="badge {{ $document->statusClass() }} flex-shrink-0">{{ $document->statusLabel() }}</span>
            </div>
          @empty
            <p class="text-muted small mb-0">
              No documents on file.
              <a href="{{ route('dispatcher.compliance.driver', $driver->id) }}">Add one</a>.
            </p>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Trips --}}
    <div class="col-12 col-lg-7">
      @if($activeTrip)
      <div class="card mb-4 border-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ti tabler-steering-wheel me-2 text-primary"></i>On a trip now</h5>
          <span class="badge {{ $activeTrip->statusEnum()?->badgeClass() }}">
            {{ $activeTrip->statusEnum()?->label() }}
          </span>
        </div>
        <div class="card-body">
          <h6 class="mb-1">{{ $activeTrip->reference() }} &middot; {{ $activeTrip->passengerName() }}</h6>
          <p class="text-muted small mb-1">
            <i class="ti tabler-circle icon-xs me-1"></i>{{ $activeTrip->pickup_address }}
          </p>
          <p class="text-muted small mb-3">
            <i class="ti tabler-map-pin icon-xs me-1"></i>{{ $activeTrip->dropoff_address }}
          </p>
          <a href="{{ route('dispatcher.trip.details', $activeTrip->id) }}" class="btn btn-sm btn-primary">
            Open trip
          </a>
        </div>
      </div>
      @endif

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Today</h5>
          <span class="badge bg-label-secondary">{{ $todaysTrips->count() }}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Time</th>
                <th>Trip</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @forelse($todaysTrips as $trip)
              <tr>
                <td class="text-nowrap" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</td>
                <td>
                  <a href="{{ route('dispatcher.trip.details', $trip->id) }}" class="fw-semibold d-block">
                    {{ $trip->reference() }}
                  </a>
                  <small class="text-muted d-block text-truncate" style="max-width: 200px;">
                    {{ $trip->passengerName() }}
                  </small>
                </td>
                <td>
                  <span class="badge {{ $trip->statusEnum()?->badgeClass() ?? 'bg-label-secondary' }}">
                    {{ $trip->statusEnum()?->label() ?? 'Unknown' }}
                  </span>
                </td>
              </tr>
              @empty
              <tr><td colspan="3" class="text-center py-4 text-muted">Nothing scheduled today.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      @if($incidents->isNotEmpty())
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Recent incidents</h5>
        </div>
        <div class="list-group list-group-flush">
          @foreach($incidents as $incident)
          <a href="{{ route('dispatcher.incidents.show', $incident->id) }}"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
            <div class="min-w-0">
              <span class="d-block text-truncate">
                @if($incident->isSos())<i class="ti tabler-urgent text-danger me-1"></i>@endif
                {{ $incident->headline() }}
              </span>
              <small class="text-muted">{{ $incident->created_at->diffForHumans() }}</small>
            </div>
            <span class="badge {{ $incident->statusClass() }} flex-shrink-0">{{ $incident->statusLabel() }}</span>
          </a>
          @endforeach
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
@endsection
