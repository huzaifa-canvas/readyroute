@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('content')
<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Dashboard /</span> Overview
</h4>

<div class="row">
  <div class="col-12 mb-4">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Welcome to the Dashboard!</h4>
        <p class="card-text">
          This is your central hub for managing users. Use the sidebar to navigate to the user management section.
        </p>
      </div>
    </div>
  </div>
  
  <div class="col-sm-6 col-lg-3 mb-4">
    <div class="card card-border-shadow-primary">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-users"></i></span>
          </div>
          <h4 class="ms-1 mb-0">{{ \App\Models\User::count() }}</h4>
        </div>
        <p class="mb-1">Total Users</p>
        <p class="mb-0">
          <span class="fw-medium me-1">Registered accounts</span>
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
