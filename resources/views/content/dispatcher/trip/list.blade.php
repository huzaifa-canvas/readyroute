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
    <form action="{{ route('dispatcher.trip.list') }}" method="GET" class="row g-3">
      <div class="col-md-5">
        <label class="form-label" for="search">Search</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search passenger, address..." value="{{ request('search') }}" />
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
      <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-label-primary w-100 me-2">Filter</button>
        @if(request('search') || request('status'))
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
            <small class="text-muted"><i class="ti tabler-clock icon-xs me-1"></i>{{ $trip->pickup_time }}</small>
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
            @if($trip->status == 'scheduled')
              <span class="badge bg-label-warning">Scheduled</span>
            @elseif($trip->status == 'in_progress')
              <span class="badge bg-label-primary">In Progress</span>
            @elseif($trip->status == 'completed')
              <span class="badge bg-label-success">Completed</span>
            @elseif($trip->status == 'cancelled')
              <span class="badge bg-label-danger">Cancelled</span>
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
              <form action="{{ route('dispatcher.trip.delete', $trip->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this trip?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-icon btn-sm btn-label-danger" title="Delete Trip">
                  <i class="ti tabler-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="text-center py-5">
            <i class="ti tabler-route text-muted mb-3" style="font-size: 3rem;"></i>
            <h5>No trips scheduled yet</h5>
            <p class="text-muted mb-3">Create your first trip to get started.</p>
            <a href="{{ route('dispatcher.trip.create') }}" class="btn btn-primary">Create New Trip</a>
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
