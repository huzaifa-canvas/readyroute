@extends('layouts/layoutMaster')

@section('title', 'Trip List - Dispatcher')

@section('content')

@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center mb-5">
  <h4 class="fw-bold mb-0">Trip Management</h4>
  <a href="{{ route('dispatcher.trip.create') }}" class="btn btn-primary">
    <i class="icon-base ti tabler-plus me-1"></i> Create New Trip
  </a>
</div>

{{-- Search & Filters --}}
<div class="card mb-5">
  <div class="card-body">
    @php
      $todayDate = today()->toDateString();
      $isToday = request('date_from') === $todayDate && request('date_to') === $todayDate;
      $hasFilters = collect(request()->only(['search', 'status', 'date_from', 'date_to', 'driver_id']))->filter()->isNotEmpty();
    @endphp
    <form action="{{ route('dispatcher.trip.list') }}" method="GET" class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="search">Search</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search passenger, address..." value="{{ request('search') }}" />
      </div>
      <div class="col-md-4">
        <label class="form-label" for="driver_id">Driver</label>
        <select id="driver_id" name="driver_id" class="form-select">
          <option value="">All Drivers</option>
          <option value="unassigned" {{ request('driver_id') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
          @foreach($drivers as $driver)
            <option value="{{ $driver->id }}" {{ (string) request('driver_id') === (string) $driver->id ? 'selected' : '' }}>{{ $driver->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
          <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
          <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
          <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="date_from">From Date</label>
        <input type="date" id="date_from" name="date_from" class="form-control" value="{{ request('date_from') }}" />
      </div>
      <div class="col-md-3">
        <label class="form-label" for="date_to">To Date</label>
        <input type="date" id="date_to" name="date_to" class="form-control" value="{{ request('date_to') }}" />
      </div>
      <div class="col-md-6 d-flex flex-wrap align-items-end gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="ti tabler-filter me-1"></i> Filter
        </button>
        {{-- Keeps the other filters and swaps the dates for today's. --}}
        <a href="{{ route('dispatcher.trip.list', array_merge(request()->except(['date_from', 'date_to', 'page']), ['date_from' => $todayDate, 'date_to' => $todayDate])) }}"
           class="btn {{ $isToday ? 'btn-info' : 'btn-label-info' }}">
          <i class="ti tabler-calendar-event me-1"></i> Today's Trips
        </a>
        {{-- Same idea for trips still waiting on a driver. --}}
        <a href="{{ route('dispatcher.trip.list', array_merge(request()->except(['driver_id', 'page']), ['driver_id' => 'unassigned'])) }}"
           class="btn {{ request('driver_id') === 'unassigned' ? 'btn-warning' : 'btn-label-warning' }}">
          <i class="ti tabler-user-question me-1"></i> Unassigned Trips
        </a>
        @if($hasFilters)
          <a href="{{ route('dispatcher.trip.list') }}" class="btn btn-label-secondary">Clear</a>
        @endif
      </div>
    </form>
  </div>
</div>

{{-- Trips Table --}}
<div class="card">
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Passenger</th>
          <th>Date & Time</th>
          <th>Pickup / Drop-Off</th>
          <th>Type</th>
          <th>Driver & Vehicle</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse($trips as $trip)
        <tr>
          <td>
            <div class="fw-bold">{{ $trip->first_name }} {{ $trip->last_name }}</div>
            <small class="text-muted">{{ $trip->phone_number ?: 'No Phone' }}</small>
          </td>
          <td>
            <div><i class="ti tabler-calendar icon-xs me-1"></i>{{ $trip->pickup_date ? $trip->pickup_date->format('M d, Y') : '-' }}</div>
            <small class="text-muted"><i class="ti tabler-clock icon-xs me-1"></i>{{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</small>
          </td>
          <td>
            <div class="text-truncate" style="max-width: 200px;" title="{{ $trip->pickup_address }}"><span class="badge bg-label-success p-1 me-1">From</span> {{ $trip->pickup_address }}</div>
            <div class="text-truncate" style="max-width: 200px;" title="{{ $trip->dropoff_address }}"><span class="badge bg-label-danger p-1 me-1">To</span> {{ $trip->dropoff_address }}</div>
            @if($trip->distance)
              <div class="small text-muted mt-1"><i class="ti tabler-route icon-xs me-1 text-primary"></i><strong>{{ $trip->distance }} Miles</strong></div>
            @endif
          </td>
          <td>
            <span class="badge bg-label-info text-capitalize">{{ str_replace('_', ' ', $trip->trip_type) }}</span>
          </td>
          <td>
            <div><strong>Driver:</strong> {{ $trip->driver ? $trip->driver->name : 'Auto-Assign' }}</div>
            <small class="text-muted"><strong>Vehicle:</strong> {{ $trip->vehicle ? $trip->vehicle->name : 'Unassigned' }}</small>
          </td>
          <td>
            @php($tripStatus = $trip->statusEnum())
            @if($tripStatus)
              <span class="badge {{ $tripStatus->badgeClass() }}">{{ $tripStatus->label() }}</span>
            @endif
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <a href="{{ route('dispatcher.trip.details', $trip->id) }}" class="btn btn-icon btn-sm btn-label-info" title="View Trip Details">
                <i class="ti tabler-eye"></i>
              </a>
              <a href="{{ route('dispatcher.trip.edit', $trip->id) }}" class="btn btn-icon btn-sm btn-label-primary" title="Edit Trip">
                <i class="ti tabler-edit"></i>
              </a>
              {{-- Cancel marks a booked run as not happening and keeps it on
                   the board. A finished or already cancelled trip has nothing
                   left to cancel, so the control is simply not offered. --}}
              @if(! $tripStatus?->isTerminal() && auth()->user()->hasPermission('trips.cancel'))
                <form action="{{ route('dispatcher.trip.cancel', $trip->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this trip? It stays on the record and the assigned driver is notified.');">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-icon btn-sm btn-label-warning" title="Cancel Trip">
                    <i class="ti tabler-ban"></i>
                  </button>
                </form>
              @endif

              {{-- Delete takes the trip off the panel altogether, for one that
                   should never have been booked. --}}
              @if(auth()->user()->hasPermission('trips.delete'))
                <form action="{{ route('dispatcher.trip.delete', $trip->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this trip? It is removed from the panel completely. To record that a booked trip did not happen, cancel it instead.');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-icon btn-sm btn-label-danger" title="Delete Trip">
                    <i class="ti tabler-trash"></i>
                  </button>
                </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center py-5">
            <i class="ti tabler-route text-muted mb-3" style="font-size: 3rem;"></i>
            @if($hasFilters)
              <h5>No trips match these filters</h5>
              <p class="text-muted mb-3">Try a different date range or driver.</p>
              <a href="{{ route('dispatcher.trip.list') }}" class="btn btn-label-secondary">Clear Filters</a>
            @else
              <h5>No trips scheduled yet</h5>
              <p class="text-muted mb-3">Create your first trip to get started.</p>
              <a href="{{ route('dispatcher.trip.create') }}" class="btn btn-primary">Create New Trip</a>
            @endif
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($trips->hasPages())
  <div class="card-footer d-flex justify-content-center">
    {{ $trips->links() }}
  </div>
  @endif
</div>

@endsection
