@extends('layouts/layoutMaster')

@section('title', 'My Drivers - Dispatcher')

@section('content')

{{-- Success/Error Messages --}}
@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<h4 class="mb-1">Drivers</h4>
<p class="mb-6">Manage your assigned drivers and credentials.</p>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-4">
    <h5 class="mb-0">Driver List</h5>
    <div class="d-flex gap-3 align-items-center">
      <a href="{{ route('dispatcher.driver.create') }}" class="btn btn-primary">
        <i class="icon-base ti tabler-plus me-2"></i> Add New Driver
      </a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Driver</th>
          <th>Phone</th>
          <th>License #</th>
          <th>CDL Class</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($drivers as $index => $driver)
        <tr>
          <td>{{ $drivers->firstItem() + $index }}</td>
          <td>
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm me-3">
                <img src="{{ $driver->avatar_url }}" alt="Avatar" class="rounded-circle" />
              </div>
              <div>
                <span class="fw-medium d-block">{{ $driver->name }}</span>
                <small class="text-muted">{{ $driver->email }}</small>
              </div>
            </div>
          </td>
          <td>{{ $driver->phone_number ?? '-' }}</td>
          <td>{{ $driver->getMeta('driver_license_number', '-') }}</td>
          <td>{{ $driver->getMeta('cdl_class', '-') }}</td>
          <td>
            <div class="d-flex gap-1">
              <a href="{{ route('dispatcher.driver.edit', $driver->id) }}" class="btn btn-icon btn-text-primary btn-sm" title="Edit Driver">
                <i class="icon-base ti tabler-edit icon-md"></i>
              </a>
              <form action="{{ route('dispatcher.driver.delete', $driver->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-icon btn-text-danger btn-sm" onclick="return confirm('Are you sure you want to delete this driver?')" title="Delete Driver">
                  <i class="icon-base ti tabler-trash icon-md"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">No drivers found. Click "Add New Driver" to create one.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($drivers->hasPages())
  <div class="card-footer d-flex justify-content-center">
    {{ $drivers->links() }}
  </div>
  @endif
</div>
@endsection
