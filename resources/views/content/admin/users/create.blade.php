@extends('layouts/layoutMaster')

@section('title', 'Add New User - Admin')

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
  <div class="col-xl-8 col-lg-10 col-md-10 mx-auto">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Add New User</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.user.store') }}" enctype="multipart/form-data">
          @csrf
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="role">Role</label>
              <select id="role" name="role" class="form-select" required>
                <option value="dispatcher" {{ old('role') === 'dispatcher' ? 'selected' : '' }}>Dispatcher (Default)</option>
                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="driver" {{ old('role') === 'driver' ? 'selected' : '' }}>Driver</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="phone_number">Phone Number</label>
              <input type="text" id="phone_number" name="phone_number" class="form-control" value="{{ old('phone_number') }}" placeholder="+1 234 567 890" />
            </div>

            <div class="col-md-12">
              <label class="form-label" for="profile_image">Profile Image</label>
              <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="password">Password</label>
              <input type="password" id="password" name="password" class="form-control" placeholder="············" required minlength="8" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="password_confirmation">Confirm Password</label>
              <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="············" required minlength="8" />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Create User</button>
            <a href="{{ route('admin.user.list') }}" class="btn btn-label-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
