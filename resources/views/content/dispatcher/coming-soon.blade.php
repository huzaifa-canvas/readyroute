@extends('layouts/layoutMaster')

@section('title', 'Coming Soon - Dispatcher')

@section('content')
<div class="misc-wrapper text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 60vh;">
  <h2 class="mb-2 mx-2">Feature in Development! 🚧</h2>
  <p class="mb-4 mx-2">We are working hard to bring you this feature. Stay tuned!</p>
  <div class="mt-4">
    <img src="{{ asset('assets/img/illustrations/page-misc-under-maintenance.png') }}" alt="page-misc-under-maintenance" width="300" class="img-fluid">
  </div>
  <a href="{{ route('dispatcher.dashboard') }}" class="btn btn-primary mt-5">Back to Dashboard</a>
</div>
@endsection
