@extends('layouts/layoutMaster')

@section('title', isset($vehicle) ? 'Edit Vehicle - Fleet Management' : 'Add Vehicle - Fleet Management')

@section('content')

@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if($errors->any())
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    @foreach($errors->all() as $error)
      <p class="mb-0">{{ $error }}</p>
    @endforeach
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  <div class="col-xl-8 col-lg-10 col-md-12 mx-auto">
    <div class="d-flex align-items-center mb-4">
      <a href="{{ route('dispatcher.fleet.index') }}" class="btn btn-icon btn-label-secondary me-3">
        <i class="icon-base ti tabler-arrow-left"></i>
      </a>
      <h3 class="mb-0 fw-bold">{{ isset($vehicle) ? 'Edit Vehicle' : 'Add New Vehicle' }}</h3>
    </div>

    <form method="POST" action="{{ isset($vehicle) ? route('dispatcher.fleet.update', $vehicle->id) : route('dispatcher.fleet.store') }}" enctype="multipart/form-data">
      @csrf
      @if(isset($vehicle))
        @method('PUT')
      @endif

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0 fw-semibold">Vehicle Details</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12 text-center mb-4">
              <img src="{{ isset($vehicle) ? $vehicle->image_url : asset('assets/img/illustrations/page-misc-under-maintenance.png') }}" id="vehicleImagePreview" alt="Vehicle Image" class="rounded border" style="max-width: 200px; max-height: 150px; object-fit: cover;">
              <div class="mt-3">
                <label for="image" class="btn btn-outline-primary btn-sm" tabindex="0">
                  <i class="ti tabler-upload me-1"></i> Upload Image
                  <input type="file" id="image" name="image" class="account-file-input" hidden accept="image/png, image/jpeg, image/webp" onchange="document.getElementById('vehicleImagePreview').src = window.URL.createObjectURL(this.files[0])" />
                </label>
              </div>
            </div>
            
            <div class="col-md-12">
              <label class="form-label" for="name">Vehicle Name / ID <span class="text-danger">*</span></label>
              <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Van #101" value="{{ old('name', $vehicle->name ?? '') }}" required />
            </div>
            
            <div class="col-md-6">
              <label class="form-label" for="make_model_year">Make / Model</label>
              <input type="text" id="make_model_year" name="make_model_year" class="form-control" placeholder="e.g. Honda Odyssey EX-L" value="{{ old('make_model_year', $vehicle->make_model_year ?? '') }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="year">Year</label>
              <input type="text" id="year" name="year" class="form-control" placeholder="e.g. 2022" value="{{ old('year', $vehicle->year ?? '') }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="color">Color</label>
              <input type="text" id="color" name="color" class="form-control" placeholder="e.g. Silver" value="{{ old('color', $vehicle->color ?? '') }}" />
            </div>
            
            <div class="col-md-6">
              <label class="form-label" for="number_plate">License Plate</label>
              <input type="text" id="number_plate" name="number_plate" class="form-control" placeholder="e.g. NY - MDT-4821" value="{{ old('number_plate', $vehicle->number_plate ?? '') }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="status">Current Status <span class="text-danger">*</span></label>
              <select id="status" name="status" class="form-select" required>
                <option value="available" {{ old('status', $vehicle->status ?? '') == 'available' ? 'selected' : '' }}>Available</option>
                <option value="in_use" {{ old('status', $vehicle->status ?? '') == 'in_use' ? 'selected' : '' }}>In Use</option>
                <option value="maintenance" {{ old('status', $vehicle->status ?? '') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="vin_number">VIN</label>
              <input type="text" id="vin_number" name="vin_number" class="form-control" placeholder="e.g. 1HGBH41JXMN109186" value="{{ old('vin_number', $vehicle->vin_number ?? '') }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="seating_capacity">Seating Capacity</label>
              <input type="number" id="seating_capacity" name="seating_capacity" class="form-control" placeholder="e.g. 7" value="{{ old('seating_capacity', $vehicle->seating_capacity ?? '') }}" min="1" />
            </div>

            <div class="col-md-6 d-flex align-items-end mb-2">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="wheelchair_ramp" name="wheelchair_ramp" value="1" {{ old('wheelchair_ramp', $vehicle->wheelchair_ramp ?? false) ? 'checked' : '' }} />
                <label class="form-check-label" for="wheelchair_ramp">Wheelchair Ramp (built-in)</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-5">
        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold">{{ isset($vehicle) ? 'Update Vehicle' : 'Save Vehicle' }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
