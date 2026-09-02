@extends('layouts/layoutMaster')

@section('title', 'Trip #' . $trip->id . ' Details')

@section('content')

{{-- Header Action Bar --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="{{ route('dispatcher.trip.list') }}" class="btn btn-icon btn-label-secondary me-2">
      <i class="ti tabler-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Trip #{{ $trip->id }}</h4>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('dispatcher.trip.edit', $trip->id) }}" class="btn btn-outline-primary px-4">
      <i class="ti tabler-edit me-1"></i> Edit Trip
    </a>
    <form action="{{ route('dispatcher.trip.delete', $trip->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel/delete this trip?');">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn btn-danger px-4">
        Cancel Trip
      </button>
    </form>
  </div>
</div>

{{-- Status Banner --}}
@php
  $statusBg = 'bg-label-warning text-warning border-warning';
  $statusText = 'Scheduled';
  if ($trip->status === 'in_progress') {
    $statusBg = 'bg-label-success text-success border-success';
    $statusText = 'En Route to Pickup / In Progress';
  } elseif ($trip->status === 'completed') {
    $statusBg = 'bg-label-primary text-primary border-primary';
    $statusText = 'Trip Completed';
  } elseif ($trip->status === 'cancelled') {
    $statusBg = 'bg-label-danger text-danger border-danger';
    $statusText = 'Trip Cancelled';
  }
@endphp

<div class="card mb-4 border shadow-none {{ $statusBg }} p-3">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <small class="d-block opacity-75 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Current Status</small>
      <h5 class="fw-bold mb-0 text-inherit">{{ $statusText }}</h5>
    </div>
    <div class="text-end">
      <small class="opacity-75"><i class="ti tabler-clock me-1"></i>Pickup: {{ \Carbon\Carbon::parse($trip->pickup_date)->format('M d, Y') }} at {{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</small>
    </div>
  </div>
</div>

{{-- Row 1: Passenger Info & Route --}}
<div class="row g-4 mb-4">
  {{-- Passenger Info Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <small class="text-muted d-block">Member ID: {{ $trip->member_id ?: 'MB-' . (1000 + $trip->id) }}</small>
            <h5 class="fw-bold mb-1">{{ $trip->first_name }} {{ $trip->last_name }}</h5>
          </div>
          <div class="d-flex gap-1 flex-wrap">
            @if($trip->req_wheelchair)
              <span class="badge bg-label-warning rounded-pill"><i class="ti tabler-wheelchair me-1"></i>Wheelchair</span>
            @endif
            @if($trip->req_stretcher)
              <span class="badge bg-label-info rounded-pill"><i class="ti tabler-bed me-1"></i>Stretcher</span>
            @endif
            @if($trip->billing_type)
              <span class="badge bg-label-secondary rounded-pill text-capitalize">{{ $trip->billing_type }}</span>
            @endif
          </div>
        </div>

        <div class="mb-2 text-muted small">
          <i class="ti tabler-phone text-primary me-2"></i>{{ $trip->phone_number ?: 'N/A' }}
        </div>
        <div class="text-muted small">
          <i class="ti tabler-id text-primary me-2"></i>Member ID: {{ $trip->member_id ?: 'N/A' }}
        </div>
      </div>
    </div>
  </div>

  {{-- Route Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">Route</h6>
          <div class="small text-muted">
            <span class="me-3"><i class="ti tabler-route text-primary me-1"></i>Est. Distance: {{ $trip->distance ? $trip->distance . ' mi' : 'N/A' }}</span>
          </div>
        </div>

        <div class="position-relative pt-1 pb-1">
          {{-- Dotted Line connecting pins --}}
          <div class="position-absolute" style="left: 14px; top: 15px; bottom: 15px; border-left: 2px dashed #cfd4dc; z-index: 1;"></div>

          {{-- Pickup --}}
          <div class="d-flex align-items-center gap-3 mb-3 position-relative" style="z-index: 2;">
            <div class="avatar avatar-sm flex-shrink-0">
              <span class="avatar-initial rounded-circle" style="background-color: #eeeffb; color: #435ebe; font-size: 13px;">
                <i class="ti tabler-map-pin"></i>
              </span>
            </div>
            <div>
              <small class="text-muted d-block" style="font-size: 0.82rem;">Pickup at {{ \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') }}</small>
              <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;">{{ $trip->pickup_address }}</h6>
            </div>
          </div>

          {{-- Drop-Off --}}
          <div class="d-flex align-items-center gap-3 position-relative" style="z-index: 2;">
            <div class="avatar avatar-sm flex-shrink-0">
              <span class="avatar-initial rounded-circle" style="background-color: #eeeffb; color: #435ebe; font-size: 13px;">
                <i class="ti tabler-map-pin"></i>
              </span>
            </div>
            <div>
              <small class="text-muted d-block" style="font-size: 0.82rem;">Drop-Off</small>
              <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;">{{ $trip->dropoff_address }}</h6>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Row 2: Action Required / Notes Banner --}}
@if($trip->notes || $trip->req_wheelchair || $trip->req_stretcher || $trip->req_o2_tank)
<div class="card mb-4 bg-label-warning border-warning shadow-none">
  <div class="card-body p-3 d-flex align-items-start gap-3">
    <div class="avatar avatar-sm flex-shrink-0 bg-warning text-white rounded d-flex align-items-center justify-content-center">
      <i class="ti tabler-alert-triangle"></i>
    </div>
    <div>
      <h6 class="fw-bold mb-1 text-dark">Action Required / Special Instructions</h6>
      <p class="mb-0 small text-dark opacity-90">
        @if($trip->notes)
          {{ $trip->notes }}
        @else
          Client requires special assistance. Ensure proper equipment is prepared before pickup.
        @endif
      </p>
    </div>
  </div>
</div>
@endif

{{-- Row 3: Driver & Vehicle + Trip Log --}}
<div class="row g-4">
  {{-- Driver & Vehicle Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-lg">
              <span class="avatar-initial rounded-circle bg-label-primary fs-4 fw-bold">
                {{ $trip->driver ? strtoupper(substr($trip->driver->name, 0, 2)) : 'DR' }}
              </span>
            </div>
            <div>
              <h6 class="fw-bold mb-0">{{ $trip->driver ? $trip->driver->name : 'Unassigned Driver' }}</h6>
              <small class="text-success fw-semibold"><i class="ti tabler-circle-filled icon-xs me-1"></i>Online • Ready</small>
            </div>
          </div>
          <span class="badge bg-label-info">Driver & Vehicle</span>
        </div>

        <div class="mb-3 small">
          <div class="text-muted"><strong class="text-dark">Vehicle:</strong> {{ $trip->vehicle ? $trip->vehicle->name : 'Unassigned' }}</div>
          <div class="text-muted"><strong class="text-dark">License Plate:</strong> {{ $trip->vehicle ? ($trip->vehicle->license_plate ?: 'TX-VAN-4821') : 'N/A' }}</div>
        </div>

        <a href="tel:{{ $trip->driver ? $trip->driver->phone : '' }}" class="btn btn-primary w-100">
          <i class="ti tabler-message me-1"></i> Contact Driver
        </a>
      </div>
    </div>
  </div>

  {{-- Trip Log Card --}}
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-0">
      <div class="card-body">
        <h6 class="fw-bold mb-4">Trip Log</h6>

        <ul class="timeline ms-1 mb-0 list-unstyled">
          <li class="d-flex align-items-start mb-3">
            <span class="badge bg-success rounded-circle p-1 me-3 mt-1"><i class="ti tabler-check"></i></span>
            <div class="d-flex justify-content-between w-100">
              <div>
                <strong class="d-block small">Confirmed & Scheduled</strong>
                <small class="text-muted">Trip details registered in system</small>
              </div>
              <small class="text-muted">{{ $trip->created_at->format('h:i A') }}</small>
            </div>
          </li>

          <li class="d-flex align-items-start mb-3">
            <span class="badge bg-primary rounded-circle p-1 me-3 mt-1"><i class="ti tabler-bell"></i></span>
            <div class="d-flex justify-content-between w-100">
              <div>
                <strong class="d-block small">Driver Assigned</strong>
                <small class="text-muted">{{ $trip->driver ? $trip->driver->name : 'Pending assignment' }}</small>
              </div>
              <small class="text-muted">{{ $trip->created_at->addMinutes(5)->format('h:i A') }}</small>
            </div>
          </li>

          <li class="d-flex align-items-start">
            <span class="badge bg-warning rounded-circle p-1 me-3 mt-1"><i class="ti tabler-car"></i></span>
            <div class="d-flex justify-content-between w-100">
              <div>
                <strong class="d-block small">Status: {{ ucfirst(str_replace('_', ' ', $trip->status)) }}</strong>
                <small class="text-muted">Current status update</small>
              </div>
              <small class="text-muted">{{ $trip->updated_at->format('h:i A') }}</small>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

@endsection
