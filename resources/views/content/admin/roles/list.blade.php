@extends('layouts/layoutMaster')

@section('title', 'Role Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Role Management</h4>
      <p class="text-muted mb-0 small">
        Permission templates every company can assign to its panel users.
      </p>
    </div>
    <a href="{{ route('admin.role.create') }}" class="btn btn-primary">
      <i class="ti tabler-plus me-1"></i> Create Role
    </a>
  </div>

  <div class="row g-4">
    @forelse($roles as $role)
    @php($granted = $role->permissionCount())
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">

          <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
            <h5 class="card-title fw-bold mb-0 text-break">{{ $role->name }}</h5>
            @if($role->is_system)
              <span class="badge bg-label-primary flex-shrink-0">System</span>
            @endif
          </div>

          <p class="text-muted small flex-grow-1">
            {{ $role->description ?: 'No description.' }}
          </p>

          {{-- How much of the platform this role unlocks --}}
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <small class="text-muted">Permissions</small>
              <small class="fw-semibold">{{ $granted }} / {{ $totalPermissions }}</small>
            </div>
            <div class="progress" style="height: 6px;" role="progressbar"
                 aria-label="Permissions granted" aria-valuenow="{{ $granted }}"
                 aria-valuemin="0" aria-valuemax="{{ $totalPermissions }}">
              <div class="progress-bar bg-primary"
                   style="width: {{ $totalPermissions > 0 ? round($granted / $totalPermissions * 100) : 0 }}%"></div>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between mb-3">
            <small class="text-muted">
              <i class="ti tabler-users icon-xs me-1"></i>
              {{ $role->users_count }} {{ $role->users_count === 1 ? 'user' : 'users' }}
            </small>
          </div>

          <div class="d-flex gap-2">
            <a href="{{ route('admin.role.edit', $role->id) }}" class="btn btn-outline-primary flex-grow-1">
              <i class="ti tabler-edit me-1"></i>Edit
            </a>
            @if($role->isDeletable())
              <form action="{{ route('admin.role.delete', $role->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger"
                        aria-label="Delete {{ $role->name }}"
                        onclick="return confirm('Delete the {{ $role->name }} role?')">
                  <i class="ti tabler-trash"></i>
                </button>
              </form>
            @else
              <button type="button" class="btn btn-outline-secondary" disabled
                      title="System roles cannot be deleted">
                <i class="ti tabler-lock"></i>
              </button>
            @endif
          </div>
        </div>
      </div>
    </div>
    @empty
    <div class="col-12">
      <div class="card">
        <div class="card-body text-center py-5 text-muted">
          <i class="ti tabler-shield-lock fs-1 d-block mb-2 text-secondary"></i>
          <h5>No roles yet</h5>
          <p class="mb-3">Create a role to start assigning permissions.</p>
          <a href="{{ route('admin.role.create') }}" class="btn btn-primary">Create Role</a>
        </div>
      </div>
    </div>
    @endforelse
  </div>
</div>
@endsection
