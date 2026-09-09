@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? 'Coming Soon')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex justify-content-center align-items-center" style="min-height: 55vh;">
    <div class="text-center">
      <div class="mb-4">
        <i class="ti tabler-clock-hour-4 text-primary" style="font-size: 5rem; opacity: 0.35;"></i>
      </div>
      <h2 class="fw-bold text-dark mb-2">{{ $pageTitle ?? 'Coming Soon' }}</h2>
      <p class="text-muted mb-4" style="max-width: 420px; margin: 0 auto;">
        This feature is currently under development. It will be available in an upcoming update. Stay tuned!
      </p>
      <a href="{{ route('admin.dashboard') }}" class="btn btn-primary px-4">
        <i class="ti tabler-arrow-left me-1"></i> Back to System Overview
      </a>
    </div>
  </div>
</div>
@endsection
