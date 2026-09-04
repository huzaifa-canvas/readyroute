@extends('layouts/layoutMaster')

@section('title', 'Edit Driver - Dispatcher')

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
      <a href="{{ route('dispatcher.driver.list') }}" class="btn btn-icon btn-label-secondary me-3">
        <i class="icon-base ti tabler-arrow-left"></i>
      </a>
      <h3 class="mb-0 fw-bold">Edit Driver</h3>
    </div>

    <form method="POST" action="{{ route('dispatcher.driver.update', $driver->id) }}" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      {{-- Avatar / Profile Image --}}
      <div class="card mb-4">
        <div class="card-body text-center py-4">
          <div class="position-relative d-inline-block">
            <img id="avatar-preview" src="{{ $driver->avatar_url }}" alt="Driver Avatar" class="rounded-circle img-thumbnail" style="width: 110px; height: 110px; object-fit: cover;" />
            <label for="profile_image" class="btn btn-icon btn-primary rounded-circle position-absolute bottom-0 end-0 shadow-sm cursor-pointer" style="width: 34px; height: 34px;">
              <i class="icon-base ti tabler-camera icon-xs"></i>
              <input type="file" id="profile_image" name="profile_image" class="d-none" accept="image/*" onchange="previewImage(this)" />
            </label>
          </div>
          <p class="text-muted small mt-2 mb-0">Allowed JPG, PNG or GIF. Max size of 2MB</p>
        </div>
      </div>

      {{-- Personal Info --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0 fw-semibold">Personal Info</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" id="name" name="name" class="form-control" placeholder="Enter Full Name" value="{{ old('name', $driver->name) }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone_number">Phone Number</label>
              <input type="text" id="phone_number" name="phone_number" class="form-control" placeholder="Enter Phone No" value="{{ old('phone_number', $driver->phone_number) }}" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email Address</label>
              <input type="email" id="email" name="email" class="form-control" placeholder="Enter Email Address" value="{{ old('email', $driver->email) }}" required />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="password">Password <small class="text-muted">(leave blank to keep current)</small></label>
              <input type="password" id="password" name="password" class="form-control" placeholder="Enter New Password" minlength="8" />
            </div>
          </div>
        </div>
      </div>

      {{-- License & Credentials --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0 fw-semibold">License & Credentials</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-12">
              <label class="form-label" for="driver_license_number">Driver License #</label>
              <input type="text" id="driver_license_number" name="driver_license_number" class="form-control" placeholder="Enter Driver License No" value="{{ old('driver_license_number', $driver->getMeta('driver_license_number')) }}" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="license_state">License State</label>
              <input type="text" id="license_state" name="license_state" class="form-control" placeholder="NY" value="{{ old('license_state', $driver->getMeta('license_state')) }}" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="license_expiry_date">Expiry Date</label>
              <input type="date" id="license_expiry_date" name="license_expiry_date" class="form-control" value="{{ old('license_expiry_date', $driver->getMeta('license_expiry_date')) }}" />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="cdl_class">CDL Class</label>
              <input type="text" id="cdl_class" name="cdl_class" class="form-control" placeholder="Class B - Passenger Van" value="{{ old('cdl_class', $driver->getMeta('cdl_class')) }}" />
            </div>
          </div>
        </div>
      </div>

      {{-- Availability --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0 fw-semibold">Availability</h5>
        </div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
              <span>Monday – Friday</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="availability_mon_fri" name="availability_mon_fri" value="1" {{ old('availability_mon_fri', (string)$driver->getMeta('availability_mon_fri', '1')) === '1' ? 'checked' : '' }} />
              </div>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
              <span>Saturday</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="availability_sat" name="availability_sat" value="1" {{ old('availability_sat', (string)$driver->getMeta('availability_sat', '0')) === '1' ? 'checked' : '' }} />
              </div>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
              <span>Sunday</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="availability_sun" name="availability_sun" value="1" {{ old('availability_sun', (string)$driver->getMeta('availability_sun', '0')) === '1' ? 'checked' : '' }} />
              </div>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
              <span>On-Call / Emergency</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="availability_on_call" name="availability_on_call" value="1" {{ old('availability_on_call', (string)$driver->getMeta('availability_on_call', '1')) === '1' ? 'checked' : '' }} />
              </div>
            </li>
          </ul>
        </div>
      </div>

      {{-- Notes --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0 fw-semibold">Notes</h5>
        </div>
        <div class="card-body">
          <label class="form-label" for="internal_notes">Internal Notes (Optional)</label>
          <textarea id="internal_notes" name="internal_notes" class="form-control" rows="3" placeholder="Enter Notes...">{{ old('internal_notes', $driver->getMeta('internal_notes')) }}</textarea>
        </div>
      </div>

      {{-- Submit Button --}}
      <div class="mb-5">
        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold">Update Driver</button>
      </div>
    </form>
  </div>
</div>

<script>
function previewImage(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('avatar-preview').src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
@endsection
