@extends('layouts/layoutMaster')

@section('title', 'Client Profiles')

@section('content')

@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center mb-6">
  <h4 class="fw-bold mb-0">Client Profiles</h4>
  <a href="{{ route('dispatcher.client.create') }}" class="btn btn-primary">
    <i class="icon-base ti tabler-plus me-1"></i> Add Client
  </a>
</div>

{{-- Filters --}}
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('dispatcher.client.index') }}" method="GET" class="row g-3">
      <div class="col-md-6 col-lg-5">
        <label class="form-label" for="search">Search Clients</label>
        <div class="input-group input-group-merge">
          <span class="input-group-text" id="basic-addon-search31"><i class="icon-base ti tabler-search"></i></span>
          <input type="text" id="search" name="search" class="form-control" placeholder="Search by name or phone..." value="{{ request('search') }}" aria-label="Search..." aria-describedby="basic-addon-search31" />
        </div>
      </div>
      <div class="col-md-2 col-lg-2 d-flex align-items-end">
        <button type="submit" class="btn btn-label-primary w-100">Search</button>
      </div>
      @if(request('search'))
      <div class="col-md-12 mt-2">
        <a href="{{ route('dispatcher.client.index') }}" class="text-muted small"><i class="icon-base ti tabler-x icon-xs me-1"></i>Clear Search</a>
      </div>
      @endif
    </form>
  </div>
</div>

{{-- Clients Grid --}}
<div class="row g-4">
  @forelse($clients as $client)
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5 class="card-title fw-bold mb-0 text-truncate" title="{{ $client->full_name }}">{{ $client->full_name }}</h5>
          @if($client->funding_type)
            <span class="badge bg-label-primary rounded-pill px-3 py-1 text-capitalize">{{ $client->funding_type }}</span>
          @endif
        </div>
        <p class="card-subtitle text-muted mb-4 text-truncate">
          <i class="ti tabler-phone icon-xs me-1"></i> {{ $client->phone_number ?: 'No Phone' }}
          @if($client->age)
            &bull; {{ $client->age }} yrs
          @endif
        </p>
        
        <div class="mb-3 d-flex flex-wrap gap-2 text-muted small">
          @if($client->wheelchair_required)
            <span class="badge bg-label-secondary" title="Wheelchair Required"><i class="ti tabler-wheelchair icon-xs me-1"></i>Wheelchair</span>
          @endif
          @if($client->ambulatory_assistance)
            <span class="badge bg-label-secondary" title="Ambulatory Assistance"><i class="ti tabler-walk icon-xs me-1"></i>Ambulatory</span>
          @endif
          @if($client->stretcher_transport)
            <span class="badge bg-label-secondary" title="Stretcher Transport"><i class="ti tabler-bed icon-xs me-1"></i>Stretcher</span>
          @endif
          @if($client->bariatric_vehicle)
            <span class="badge bg-label-secondary" title="Bariatric Vehicle"><i class="ti tabler-ambulance icon-xs me-1"></i>Bariatric</span>
          @endif
        </div>

        @if($client->city || $client->zip_code)
        <p class="mb-0 text-muted small text-truncate">
          <i class="ti tabler-map-pin icon-xs me-1"></i> {{ $client->city }}{{ $client->city && $client->zip_code ? ', ' : '' }}{{ $client->zip_code }}
        </p>
        @endif
        
        <div class="d-flex justify-content-between align-items-center mt-4">
          <div class="d-flex gap-2 w-100">
            <a href="{{ route('dispatcher.client.edit', $client->id) }}" class="btn btn-outline-primary flex-grow-1" style="flex-basis: 50%;">Edit</a>
            <form action="{{ route('dispatcher.client.delete', $client->id) }}" method="POST" class="flex-grow-1" style="flex-basis: 50%;">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Are you sure you want to delete this client?')">
                Delete
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  @empty
  <div class="col-12 text-center py-5">
    <div class="empty-state">
      <i class="icon-base ti tabler-users text-muted mb-3" style="font-size: 3rem;"></i>
      <h5>No clients found</h5>
      <p class="text-muted">You haven't added any clients yet, or no clients match your search.</p>
      <a href="{{ route('dispatcher.client.create') }}" class="btn btn-primary mt-2">Add New Client</a>
    </div>
  </div>
  @endforelse
</div>

@if($clients->hasPages())
<div class="d-flex justify-content-center mt-6">
  {{ $clients->links() }}
</div>
@endif

@endsection
