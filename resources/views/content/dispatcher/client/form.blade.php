@extends('layouts/layoutMaster')

@section('title', isset($client) ? 'Edit Client Profile' : 'Add Client')

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
      <a href="{{ route('dispatcher.client.index') }}" class="btn btn-icon btn-label-secondary me-3">
        <i class="icon-base ti tabler-arrow-left"></i>
      </a>
      <h3 class="mb-0 fw-bold">{{ isset($client) ? 'Edit Client' : 'Add Client' }}</h3>
    </div>

    <form method="POST" action="{{ isset($client) ? route('dispatcher.client.update', $client->id) : route('dispatcher.client.store') }}">
      @csrf
      @if(isset($client))
        @method('PUT')
      @endif

      {{-- Personal Info --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-semibold">Personal Info</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
              <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter Full Name" value="{{ old('full_name', $client->full_name ?? '') }}" required />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="dob">Date Of Birth</label>
              <input type="date" id="dob" name="dob" class="form-control" placeholder="Enter Date Of Birth" value="{{ old('dob', isset($client) && $client->dob ? $client->dob->format('Y-m-d') : '') }}" />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="phone_number">Phone Number</label>
              <input type="text" id="phone_number" name="phone_number" class="form-control" placeholder="Enter Phone Number" value="{{ old('phone_number', $client->phone_number ?? '') }}" />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="email">Email (Optional)</label>
              <input type="email" id="email" name="email" class="form-control" placeholder="Enter Email Address" value="{{ old('email', $client->email ?? '') }}" />
            </div>
          </div>
        </div>
      </div>

      {{-- Address --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-semibold">Address</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <label class="form-label" for="home_address">Home Address</label>
              <input type="text" id="home_address" name="home_address" class="form-control" placeholder="Enter Home Address" value="{{ old('home_address', $client->home_address ?? '') }}" />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="apt_unit">Apt / Unit (Optional)</label>
              <input type="text" id="apt_unit" name="apt_unit" class="form-control" placeholder="Enter Apt/Unit" value="{{ old('apt_unit', $client->apt_unit ?? '') }}" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="city">City</label>
              <input type="text" id="city" name="city" class="form-control" placeholder="City" value="{{ old('city', $client->city ?? '') }}" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="zip_code">ZIP Code</label>
              <input type="text" id="zip_code" name="zip_code" class="form-control" placeholder="ZIP Code" value="{{ old('zip_code', $client->zip_code ?? '') }}" />
            </div>
          </div>
        </div>
      </div>

      {{-- Funding & Insurance --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-semibold">Funding & Insurance</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <div class="btn-group w-100 flex-wrap" role="group" aria-label="Funding Type">
                <input type="radio" class="btn-check" name="funding_type" id="funding_medicaid" value="medicaid" {{ old('funding_type', $client->funding_type ?? '') == 'medicaid' ? 'checked' : '' }}>
                <label class="btn btn-outline-primary" for="funding_medicaid">Medicaid</label>

                <input type="radio" class="btn-check" name="funding_type" id="funding_medicare" value="medicare" {{ old('funding_type', $client->funding_type ?? '') == 'medicare' ? 'checked' : '' }}>
                <label class="btn btn-outline-primary" for="funding_medicare">Medicare</label>

                <input type="radio" class="btn-check" name="funding_type" id="funding_private" value="private" {{ old('funding_type', $client->funding_type ?? '') == 'private' ? 'checked' : '' }}>
                <label class="btn btn-outline-primary" for="funding_private">Private</label>
                
                <input type="radio" class="btn-check" name="funding_type" id="funding_insurance" value="insurance" {{ old('funding_type', $client->funding_type ?? '') == 'insurance' ? 'checked' : '' }}>
                <label class="btn btn-outline-primary" for="funding_insurance">Insurance</label>
              </div>
            </div>
            <div class="col-md-12 mt-3">
              <label class="form-label" for="insurance_id">Medicaid / Insurance ID</label>
              <input type="text" id="insurance_id" name="insurance_id" class="form-control" placeholder="Enter Medicaid / Insurance ID" value="{{ old('insurance_id', $client->insurance_id ?? '') }}" />
            </div>
          </div>
        </div>
      </div>

      {{-- Mobility Needs --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-semibold">Mobility Needs</h5>
        </div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <label class="form-check-label mb-0" for="wheelchair_required">Wheelchair Required</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="wheelchair_required" name="wheelchair_required" value="1" {{ old('wheelchair_required', $client->wheelchair_required ?? false) ? 'checked' : '' }}>
              </div>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <label class="form-check-label mb-0" for="ambulatory_assistance">Ambulatory Assistance</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="ambulatory_assistance" name="ambulatory_assistance" value="1" {{ old('ambulatory_assistance', $client->ambulatory_assistance ?? false) ? 'checked' : '' }}>
              </div>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <label class="form-check-label mb-0" for="stretcher_transport">Stretcher Transport</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="stretcher_transport" name="stretcher_transport" value="1" {{ old('stretcher_transport', $client->stretcher_transport ?? false) ? 'checked' : '' }}>
              </div>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <label class="form-check-label mb-0" for="bariatric_vehicle">Bariatric Vehicle</label>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="bariatric_vehicle" name="bariatric_vehicle" value="1" {{ old('bariatric_vehicle', $client->bariatric_vehicle ?? false) ? 'checked' : '' }}>
              </div>
            </li>
          </ul>
        </div>
      </div>

      {{-- Emergency Contact --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-semibold">Emergency Contact</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <label class="form-label" for="emergency_contact_name">Contact Name</label>
              <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" placeholder="Enter Contact Name" value="{{ old('emergency_contact_name', $client->emergency_contact_name ?? '') }}" />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="emergency_contact_phone">Contact Phone</label>
              <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" placeholder="Enter Contact Phone" value="{{ old('emergency_contact_phone', $client->emergency_contact_phone ?? '') }}" />
            </div>
          </div>
        </div>
      </div>

      {{-- Special Notes --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-semibold">Special Notes</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <label class="form-label" for="special_notes">Notes (Medical, Behavioral, Etc.)</label>
              <textarea id="special_notes" name="special_notes" class="form-control" placeholder="Enter Notes..." rows="4">{{ old('special_notes', $client->special_notes ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-5">
        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold">{{ isset($client) ? 'Update Client' : 'Save Client' }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
