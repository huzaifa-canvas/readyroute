@extends('layouts/layoutMaster')

@section('title', 'Edit User - Apps')

@section('content')

{{-- Success/Error Messages --}}
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
  {{-- User Info Card --}}
  <div class="col-xl-4 col-lg-5 col-md-5">
    <div class="card mb-6">
      <div class="card-body text-center">
        <div class="avatar avatar-xl mx-auto mb-4">
          <img src="{{ $user->avatar_url }}" alt="Avatar" class="rounded-circle" />
        </div>
        <h4 class="mb-1">{{ $user->name }}</h4>
      </div>
    </div>
  </div>

  {{-- Edit Form --}}
  <div class="col-xl-8 col-lg-7 col-md-7">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Edit User</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('app-user-update', $user->id) }}">
          @csrf
          @method('PUT')
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required />
            </div>
            <div class="col-md-12">
              <label class="form-label" for="password">New Password <small class="text-muted">(leave blank to keep current)</small></label>
              <input type="password" id="password" name="password" class="form-control" placeholder="············" />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Save Changes</button>
            <a href="{{ route('app-user-list') }}" class="btn btn-label-secondary">Back to List</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
