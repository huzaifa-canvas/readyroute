@extends('layouts/layoutMaster')

@section('title', 'Fleet & Vehicle Management')

@section('content')

{{-- Success/Error Messages --}}
@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center mb-6">
  <h4 class="fw-bold mb-0">Fleet & Vehicle Management</h4>
  <a href="{{ route('dispatcher.fleet.create') }}" class="btn btn-primary">
    <i class="icon-base ti tabler-plus me-1"></i> Add Vehicle
  </a>
</div>

{{-- Filters --}}
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('dispatcher.fleet.index') }}" method="GET" class="row g-3">
      <div class="col-md-6 col-lg-4">
        <label class="form-label" for="search">Search</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text" id="basic-addon-search31"><i class="icon-base ti tabler-search"></i></span>
          <input type="text" id="search" name="search" class="form-control" placeholder="Search by name or plate..." value="{{ request('search') }}" aria-label="Search..." aria-describedby="basic-addon-search31" />
        </div>
      </div>
      <div class="col-md-4 col-lg-3">
        <label class="form-label" for="status">Filter by Status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="available" {{ request('status') == 'available' ? 'selected' : '' }}>Available</option>
          <option value="in_use" {{ request('status') == 'in_use' ? 'selected' : '' }}>In Use</option>
          <option value="maintenance" {{ request('status') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
        </select>
      </div>
      <div class="col-md-2 col-lg-2 d-flex align-items-end">
        <button type="submit" class="btn btn-label-primary w-100">Filter</button>
      </div>
      @if(request('search') || request('status'))
      <div class="col-md-12 mt-2">
        <a href="{{ route('dispatcher.fleet.index') }}" class="text-muted small"><i class="icon-base ti tabler-x icon-xs me-1"></i>Clear Filters</a>
      </div>
      @endif
    </form>
  </div>
</div>

{{-- Vehicles Grid --}}
<div class="row g-4">
  @forelse($vehicles as $vehicle)
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="d-flex align-items-center">
            @if($vehicle->image)
              <img src="{{ $vehicle->image_url }}" alt="Vehicle" class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
            @else
              <div class="bg-label-secondary rounded d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                <i class="ti tabler-car text-muted"></i>
              </div>
            @endif
            <h5 class="card-title fw-bold mb-0 text-truncate" title="{{ $vehicle->name }}">{{ $vehicle->name }}</h5>
          </div>
          @if($vehicle->status == 'available')
            <span class="badge bg-label-success rounded-pill px-3 py-1">Available</span>
          @elseif($vehicle->status == 'in_use')
            <span class="badge bg-label-primary rounded-pill px-3 py-1">In Use</span>
          @elseif($vehicle->status == 'maintenance')
            <span class="badge bg-label-warning rounded-pill px-3 py-1">Maintenance</span>
          @endif
        </div>
        <p class="card-subtitle text-muted mb-4 text-truncate">
          {{ $vehicle->make_model_year ?: 'Unknown Make' }} {{ $vehicle->year }} &bull; Plate: {{ $vehicle->number_plate ?: 'N/A' }}
        </p>
        
        <div class="mb-3 d-flex flex-wrap gap-2 text-muted small">
          @if($vehicle->color)
            <span class="badge bg-label-secondary"><i class="ti tabler-palette icon-xs me-1"></i>{{ $vehicle->color }}</span>
          @endif
          @if($vehicle->seating_capacity)
            <span class="badge bg-label-secondary"><i class="ti tabler-users icon-xs me-1"></i>{{ $vehicle->seating_capacity }} Seats</span>
          @endif
          @if($vehicle->wheelchair_ramp)
            <span class="badge bg-label-secondary" title="Wheelchair Ramp Built-in"><i class="ti tabler-wheelchair icon-xs me-1"></i>Ramp</span>
          @endif
        </div>
        
        <div class="d-flex justify-content-between align-items-center mt-5">
          <div class="d-flex gap-2 w-100">
            <a href="{{ route('dispatcher.fleet.edit', $vehicle->id) }}" class="btn btn-outline-primary flex-grow-1" style="flex-basis: 50%;">Edit</a>
            @if($vehicle->vin_number)
            <button type="button" class="btn btn-outline-secondary flex-grow-1" style="flex-basis: 50%;" onclick="alert('VIN Number: {{ $vehicle->vin_number }}')" title="View VIN">
              View VIN
            </button>
            @endif
          </div>
        </div>
        <div class="mt-3 text-end">
          <form action="{{ route('dispatcher.fleet.delete', $vehicle->id) }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-text-danger btn-sm p-0" onclick="return confirm('Are you sure you want to delete this vehicle?')">
              Delete Vehicle
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  @empty
  <div class="col-12 text-center py-5">
    <div class="empty-state">
      <i class="icon-base ti tabler-car text-muted mb-3" style="font-size: 3rem;"></i>
      <h5>No vehicles found</h5>
      <p class="text-muted">You haven't added any vehicles yet, or no vehicles match your filters.</p>
      <a href="{{ route('dispatcher.fleet.create') }}" class="btn btn-primary mt-2">Add New Vehicle</a>
    </div>
  </div>
  @endforelse
</div>

@if($vehicles->hasPages())
<div class="d-flex justify-content-center mt-6">
  {{ $vehicles->links() }}
</div>
@endif

@endsection
