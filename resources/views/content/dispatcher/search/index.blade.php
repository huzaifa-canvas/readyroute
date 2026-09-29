@extends('layouts/layoutMaster')

@section('title', 'Advanced Search')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="mb-4">
    <h4 class="fw-bold text-heading mb-1">Advanced Search</h4>
    <p class="text-muted mb-0 small">Across trips, clients, drivers and vehicles.</p>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('dispatcher.search') }}">
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <label class="form-label" for="q">Search</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i class="ti tabler-search"></i></span>
              <input type="text" id="q" name="q" class="form-control" autofocus
                     placeholder="Name, phone, address, plate, trip number..."
                     value="{{ request('q') }}" />
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <label class="form-label" for="scope">Look in</label>
            <select id="scope" name="scope" class="form-select">
              <option value="all"      @selected($scope === 'all')>Everything</option>
              <option value="trips"    @selected($scope === 'trips')>Trips only</option>
              <option value="clients"  @selected($scope === 'clients')>Clients only</option>
              <option value="drivers"  @selected($scope === 'drivers')>Drivers only</option>
              <option value="vehicles" @selected($scope === 'vehicles')>Vehicles only</option>
            </select>
          </div>

          <div class="col-6 col-lg-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
              <i class="ti tabler-search me-1"></i>Search
            </button>
          </div>
        </div>

        {{-- Trip-specific filters, kept out of the way until wanted --}}
        <div class="accordion accordion-flush mt-3" id="tripFilters">
          <div class="accordion-item border-0">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed px-0 shadow-none bg-transparent" type="button"
                      data-bs-toggle="collapse" data-bs-target="#filterBody"
                      aria-expanded="{{ request()->hasAny(['from','to','status','driver']) ? 'true' : 'false' }}">
                <i class="ti tabler-filter me-2"></i>Trip filters
                @if(request()->hasAny(['from','to','status','driver']))
                  <span class="badge bg-label-primary ms-2">active</span>
                @endif
              </button>
            </h2>
            <div id="filterBody" class="accordion-collapse collapse {{ request()->hasAny(['from','to','status','driver']) ? 'show' : '' }}"
                 data-bs-parent="#tripFilters">
              <div class="accordion-body px-0 pb-0">
                <div class="row g-3">
                  <div class="col-6 col-lg-3">
                    <label class="form-label" for="from">Pickup from</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ request('from') }}" />
                  </div>
                  <div class="col-6 col-lg-3">
                    <label class="form-label" for="to">Pickup to</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ request('to') }}" />
                  </div>
                  <div class="col-12 col-lg-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                      <option value="">Any status</option>
                      @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                          {{ $status->label() }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-12 col-lg-3">
                    <label class="form-label" for="driver">Driver</label>
                    <select id="driver" name="driver" class="form-select">
                      <option value="">Any driver</option>
                      @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected((int) request('driver') === $driver->id)>
                          {{ $driver->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        @if($searched)
          <div class="mt-3">
            <a href="{{ route('dispatcher.search') }}" class="text-muted small">
              <i class="ti tabler-x icon-xs me-1"></i>Clear search
            </a>
          </div>
        @endif
      </form>
    </div>
  </div>

  @if(! $searched)
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="ti tabler-search fs-1 d-block mb-2 text-secondary"></i>
        <h6>Start typing</h6>
        <p class="mb-0 small">Search a passenger, an address, a plate or a trip number.</p>
      </div>
    </div>
  @else
    <p class="text-muted small mb-3">
      {{ $total }} {{ $total === 1 ? 'result' : 'results' }}
      @if($term !== '') for &ldquo;{{ $term }}&rdquo; @endif
    </p>

    {{-- Trips --}}
    @if($results['trips']->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti tabler-route me-2"></i>Trips</h5>
        <span class="badge bg-label-primary">{{ $results['trips']->count() }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Trip</th>
              <th class="d-none d-md-table-cell">Pickup</th>
              <th class="d-none d-lg-table-cell">Driver</th>
              <th>Status</th>
              <th class="text-end"></th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @foreach($results['trips'] as $trip)
            @php($tripStatus = $trip->statusEnum())
            <tr>
              <td>
                <span class="fw-semibold d-block">{{ $trip->reference() }}</span>
                <small class="text-muted d-block text-truncate" style="max-width: 220px;">{{ $trip->passengerName() }}</small>
                <small class="text-muted d-md-none">{{ optional($trip->pickup_date)->format('d M Y') }}</small>
              </td>
              <td class="d-none d-md-table-cell">
                <span class="text-body">{{ optional($trip->pickup_date)->format('d M Y') }}</span>
                <small class="text-muted d-block text-truncate" style="max-width: 260px;">{{ $trip->pickup_address }}</small>
              </td>
              <td class="d-none d-lg-table-cell">{{ $trip->driver?->name ?? '—' }}</td>
              <td>
                <span class="badge {{ $tripStatus?->badgeClass() ?? 'bg-label-secondary' }}">
                  {{ $tripStatus?->label() ?? 'Unknown' }}
                </span>
              </td>
              <td class="text-end">
                <a href="{{ route('dispatcher.trip.details', $trip->id) }}" class="btn btn-sm btn-label-primary">
                  <i class="ti tabler-eye"></i>
                </a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    {{-- Clients --}}
    @if($results['clients']->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti tabler-user-square me-2"></i>Clients</h5>
        <span class="badge bg-label-primary">{{ $results['clients']->count() }}</span>
      </div>
      <div class="list-group list-group-flush">
        @foreach($results['clients'] as $client)
        <a href="{{ route('dispatcher.client.show', $client->id) }}"
           class="list-group-item list-group-item-action d-flex align-items-center gap-3">
          <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded-circle bg-label-primary">
              {{ strtoupper(substr($client->full_name, 0, 1)) }}
            </span>
          </div>
          <div class="min-w-0">
            <span class="fw-semibold d-block text-truncate">{{ $client->full_name }}</span>
            <small class="text-muted d-block text-truncate">
              {{ collect([$client->phone_number, $client->city])->filter()->implode(' · ') ?: 'No contact details' }}
            </small>
          </div>
        </a>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Drivers --}}
    @if($results['drivers']->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti tabler-steering-wheel me-2"></i>Drivers</h5>
        <span class="badge bg-label-primary">{{ $results['drivers']->count() }}</span>
      </div>
      <div class="list-group list-group-flush">
        @foreach($results['drivers'] as $driver)
        <a href="{{ route('dispatcher.driver.show', $driver->id) }}"
           class="list-group-item list-group-item-action d-flex align-items-center gap-3">
          <div class="avatar avatar-sm flex-shrink-0">
            <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
          </div>
          <div class="min-w-0 flex-grow-1">
            <span class="fw-semibold d-block text-truncate">{{ $driver->name }}</span>
            <small class="text-muted d-block text-truncate">{{ $driver->driver_code }} · {{ $driver->email }}</small>
          </div>
          @if($driver->isCurrentlyOnline())
            <span class="badge bg-label-success flex-shrink-0">Online</span>
          @endif
        </a>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Vehicles --}}
    @if($results['vehicles']->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti tabler-car me-2"></i>Vehicles</h5>
        <span class="badge bg-label-primary">{{ $results['vehicles']->count() }}</span>
      </div>
      <div class="list-group list-group-flush">
        @foreach($results['vehicles'] as $vehicle)
        <a href="{{ route('dispatcher.fleet.edit', $vehicle->id) }}"
           class="list-group-item list-group-item-action d-flex align-items-center gap-3">
          <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded bg-label-info"><i class="ti tabler-car"></i></span>
          </div>
          <div class="min-w-0 flex-grow-1">
            <span class="fw-semibold d-block text-truncate">{{ $vehicle->name }}</span>
            <small class="text-muted d-block text-truncate">
              {{ collect([$vehicle->number_plate, $vehicle->make_model_year])->filter()->implode(' · ') ?: 'No details' }}
            </small>
          </div>
          <span class="badge bg-label-secondary text-capitalize flex-shrink-0">{{ $vehicle->status }}</span>
        </a>
        @endforeach
      </div>
    </div>
    @endif

    @if($total === 0)
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="ti tabler-mood-empty fs-1 d-block mb-2 text-secondary"></i>
        <h6>No results</h6>
        <p class="mb-0 small">Nothing matched. Try a shorter term or widen the filters.</p>
      </div>
    </div>
    @endif
  @endif
</div>
@endsection
