@extends('layouts/layoutMaster')

@section('title', 'System Users')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php($isOwner = auth()->user()->isCompanyOwner() || auth()->user()->isAdmin())

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">System Users</h4>
      <p class="text-muted mb-0 small">Who can sign in to this panel, and what each of them may do.</p>
    </div>
    @if($isOwner)
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUser">
        <i class="ti tabler-user-plus me-1"></i>Add User
      </button>
    @endif
  </div>

  @unless($isOwner)
    <div class="alert alert-info" role="alert">
      <i class="ti tabler-info-circle me-2"></i>
      Only the company account can add or change panel users. You can see who has access here.
    </div>
  @endunless

  <div class="row g-4">

    {{-- Users --}}
    <div class="col-12 col-xl-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Users</h5>
          <span class="badge bg-label-secondary">{{ $users->count() + ($owner ? 1 : 0) }}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>User</th>
                <th class="d-none d-sm-table-cell">Role</th>
                <th class="d-none d-md-table-cell">Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              {{-- The company account itself, which cannot be edited here --}}
              @if($owner)
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-sm flex-shrink-0">
                      <img src="{{ $owner->avatar_url }}" alt="{{ $owner->name }}" class="rounded-circle" />
                    </div>
                    <div class="min-w-0">
                      <span class="fw-semibold d-block text-truncate">{{ $owner->name }}</span>
                      <small class="text-muted d-block text-truncate">{{ $owner->email }}</small>
                    </div>
                  </div>
                </td>
                <td class="d-none d-sm-table-cell">
                  <span class="badge bg-label-primary">Company account</span>
                </td>
                <td class="d-none d-md-table-cell">
                  <span class="badge bg-label-success">Active</span>
                </td>
                <td class="text-end">
                  <span class="text-muted small">Full access</span>
                </td>
              </tr>
              @endif

              @forelse($users as $user)
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-sm flex-shrink-0">
                      <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="rounded-circle" />
                    </div>
                    <div class="min-w-0">
                      <span class="fw-semibold d-block text-truncate">{{ $user->name }}</span>
                      <small class="text-muted d-block text-truncate">{{ $user->email }}</small>
                      <small class="text-muted d-sm-none d-block">
                        {{ $user->accessRole?->name ?? 'No role' }}
                      </small>
                    </div>
                  </div>
                </td>
                <td class="d-none d-sm-table-cell">
                  @if($user->accessRole)
                    <span class="badge bg-label-info">{{ $user->accessRole->name }}</span>
                  @else
                    <span class="badge bg-label-secondary">No role</span>
                  @endif
                </td>
                <td class="d-none d-md-table-cell">
                  <span class="badge {{ $user->statusClass() }}">{{ $user->statusLabel() }}</span>
                </td>
                <td class="text-end">
                  @if($isOwner)
                  <div class="dropdown">
                    <button class="btn btn-icon btn-label-secondary rounded-circle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $user->name }}">
                      <i class="ti tabler-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                      <li>
                        <button type="button" class="dropdown-item"
                                data-bs-toggle="modal" data-bs-target="#editUser{{ $user->id }}">
                          <i class="ti tabler-edit me-2"></i>Edit
                        </button>
                      </li>
                      <li><hr class="dropdown-divider" /></li>
                      @if($user->isSuspended())
                        <li>
                          <form method="POST" action="{{ route('dispatcher.users.activate', $user->id) }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-success">
                              <i class="ti tabler-player-play me-2"></i>Reactivate
                            </button>
                          </form>
                        </li>
                      @else
                        <li>
                          <form method="POST" action="{{ route('dispatcher.users.suspend', $user->id) }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-warning"
                                    onclick="return confirm('Suspend {{ $user->name }}? They will be signed out.')">
                              <i class="ti tabler-ban me-2"></i>Suspend
                            </button>
                          </form>
                        </li>
                      @endif
                      <li>
                        <form method="POST" action="{{ route('dispatcher.users.delete', $user->id) }}">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="dropdown-item text-danger"
                                  onclick="return confirm('Remove {{ $user->name }} from the panel?')">
                            <i class="ti tabler-trash me-2"></i>Remove
                          </button>
                        </form>
                      </li>
                    </ul>
                  </div>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">
                  <i class="ti tabler-users fs-2 d-block mb-2 text-secondary"></i>
                  <p class="mb-0 small">No extra panel users yet.</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Roles --}}
    <div class="col-12 col-xl-5">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Roles</h5>
            <small class="text-muted">Platform templates plus any of your own.</small>
          </div>
          @if($isOwner)
            <a href="{{ route('dispatcher.users.role.create') }}" class="btn btn-sm btn-label-primary">
              <i class="ti tabler-plus"></i>
            </a>
          @endif
        </div>
        <div class="card-body">
          @foreach($roles as $role)
            <div class="border rounded p-3 {{ ! $loop->last ? 'mb-3' : '' }}">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <h6 class="mb-0 text-break">{{ $role->name }}</h6>
                @if($role->is_system)
                  <span class="badge bg-label-primary flex-shrink-0">Platform</span>
                @else
                  <span class="badge bg-label-secondary flex-shrink-0">Custom</span>
                @endif
              </div>
              <p class="text-muted small mb-2">{{ $role->description ?: 'No description.' }}</p>
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <small class="text-muted">
                  {{ $role->permissionCount() }} permissions &middot;
                  {{ $role->users_count }} {{ $role->users_count === 1 ? 'user' : 'users' }}
                </small>
                @if($isOwner && ! $role->is_system)
                  <div class="d-flex gap-2">
                    <a href="{{ route('dispatcher.users.role.edit', $role->id) }}"
                       class="btn btn-text-secondary btn-sm p-0">Edit</a>
                    <form method="POST" action="{{ route('dispatcher.users.role.delete', $role->id) }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-text-danger btn-sm p-0"
                              onclick="return confirm('Delete the {{ $role->name }} role?')">Delete</button>
                    </form>
                  </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>

@if($isOwner)
{{-- Add user --}}
<div class="modal fade" id="addUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="POST" action="{{ route('dispatcher.users.store') }}">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Add Panel User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="new-name">Name <span class="text-danger">*</span></label>
          <input type="text" id="new-name" name="name" class="form-control" required value="{{ old('name') }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="new-email">Email <span class="text-danger">*</span></label>
          <input type="email" id="new-email" name="email" class="form-control" required value="{{ old('email') }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="new-phone">Phone</label>
          <input type="text" id="new-phone" name="phone_number" class="form-control" value="{{ old('phone_number') }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="new-role">Role <span class="text-danger">*</span></label>
          <select id="new-role" name="role_id" class="form-select" required>
            @foreach($roles as $role)
              <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <label class="form-label" for="new-password">Password <span class="text-danger">*</span></label>
            <input type="password" id="new-password" name="password" class="form-control"
                   autocomplete="new-password" required />
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="new-password-confirm">Confirm</label>
            <input type="password" id="new-password-confirm" name="password_confirmation"
                   class="form-control" autocomplete="new-password" required />
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add User</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit modals --}}
@foreach($users as $user)
<div class="modal fade" id="editUser{{ $user->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="POST" action="{{ route('dispatcher.users.update', $user->id) }}">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit {{ $user->name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="edit-name-{{ $user->id }}">Name</label>
          <input type="text" id="edit-name-{{ $user->id }}" name="name" class="form-control"
                 required value="{{ $user->name }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit-email-{{ $user->id }}">Email</label>
          <input type="email" id="edit-email-{{ $user->id }}" name="email" class="form-control"
                 required value="{{ $user->email }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit-phone-{{ $user->id }}">Phone</label>
          <input type="text" id="edit-phone-{{ $user->id }}" name="phone_number" class="form-control"
                 value="{{ $user->phone_number }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit-role-{{ $user->id }}">Role</label>
          <select id="edit-role-{{ $user->id }}" name="role_id" class="form-select" required>
            @foreach($roles as $role)
              <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
            @endforeach
          </select>
        </div>
        <hr />
        <p class="text-muted small">Leave the password fields empty to keep the current one.</p>
        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <label class="form-label" for="edit-pass-{{ $user->id }}">New password</label>
            <input type="password" id="edit-pass-{{ $user->id }}" name="password"
                   class="form-control" autocomplete="new-password" />
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="edit-pass2-{{ $user->id }}">Confirm</label>
            <input type="password" id="edit-pass2-{{ $user->id }}" name="password_confirmation"
                   class="form-control" autocomplete="new-password" />
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
@endforeach
@endif
@endsection
