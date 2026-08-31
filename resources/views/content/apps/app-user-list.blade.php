@extends('layouts/layoutMaster')

@section('title', 'User List - Apps')

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

<h4 class="mb-1">User List</h4>
<p class="mb-6">Manage all users from here.</p>

{{-- Filters + Table --}}
<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-4">
    <h5 class="mb-0">Users</h5>
    <div class="d-flex gap-3 align-items-center">
      {{-- Add User Button --}}
      <a href="{{ route('app-user-create') }}" class="btn btn-primary">
        <i class="icon-base ti tabler-plus me-2"></i> Add New User
      </a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Email</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $index => $user)
        <tr>
          <td>{{ $users->firstItem() + $index }}</td>
          <td>
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm me-3">
                <img src="{{ $user->avatar_url }}" alt="Avatar" class="rounded-circle" />
              </div>
              <span class="fw-medium">{{ $user->name }}</span>
            </div>
          </td>
          <td>{{ $user->email }}</td>
          <td>{{ $user->created_at->format('d M Y') }}</td>
          <td>
            <div class="d-flex gap-1">
              <a href="{{ route('app-user-edit', $user->id) }}" class="btn btn-icon btn-text-primary btn-sm">
                <i class="icon-base ti tabler-edit icon-md"></i>
              </a>
              @if($user->id !== auth()->id())
              <form action="{{ route('app-user-delete', $user->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-icon btn-text-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">
                  <i class="icon-base ti tabler-trash icon-md"></i>
                </button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="text-center py-4 text-muted">No users found.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  @if($users->hasPages())
  <div class="card-footer d-flex justify-content-center">
    {{ $users->links() }}
  </div>
  @endif
</div>
@endsection
