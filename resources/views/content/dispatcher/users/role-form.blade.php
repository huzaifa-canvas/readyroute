@extends('layouts/layoutMaster')

@php
  $editing  = $role->exists;
  $selected = old('permissions', $role->permissions ?? []);
@endphp

@section('title', $editing ? 'Edit Role — ' . $role->name : 'Create Role')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="row">
    <div class="col-xl-9 col-12 mx-auto">

      <div class="d-flex align-items-center mb-4">
        <a href="{{ route('dispatcher.users.index') }}" class="btn btn-icon btn-label-secondary me-3" aria-label="Back">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <div class="min-w-0">
          <h3 class="mb-0 fw-bold text-heading">{{ $editing ? 'Edit Role' : 'Create Role' }}</h3>
          <small class="text-muted">A permission set for your panel users.</small>
        </div>
      </div>

      <form method="POST"
            action="{{ $editing ? route('dispatcher.users.role.update', $role->id) : route('dispatcher.users.role.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card mb-4">
          <div class="card-body">
            <div class="row g-4">
              <div class="col-12 col-md-5">
                <label class="form-label fw-semibold" for="name">Role Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name"
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="e.g. Night Shift Dispatcher"
                       value="{{ old('name', $role->name) }}" required />
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-md-7">
                <label class="form-label fw-semibold" for="description">Description</label>
                <input type="text" id="description" name="description"
                       class="form-control"
                       placeholder="What this role is for"
                       value="{{ old('description', $role->description) }}" />
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 fw-semibold text-heading">
              <i class="ti tabler-lock-check me-2 text-primary"></i>Permissions
            </h5>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-label-secondary" id="selectAll">Select all</button>
              <button type="button" class="btn btn-sm btn-label-secondary" id="clearAll">Clear all</button>
            </div>
          </div>
          <div class="card-body pt-4">
            <div class="row g-4">
              @foreach($groups as $groupName => $permissions)
              <div class="col-12 col-lg-6">
                <div class="border rounded h-100">
                  <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-lighter">
                    <h6 class="mb-0 fw-semibold">{{ $groupName }}</h6>
                    <button type="button" class="btn btn-text-secondary btn-sm p-0 group-toggle"
                            data-group="{{ \Illuminate\Support\Str::slug($groupName) }}">Toggle</button>
                  </div>
                  <div class="p-3 d-flex flex-column gap-3">
                    @foreach($permissions as $key => $label)
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input perm-check" type="checkbox"
                               name="permissions[]" value="{{ $key }}" id="perm-{{ $key }}"
                               data-group="{{ \Illuminate\Support\Str::slug($groupName) }}"
                               @checked(in_array($key, $selected, true)) />
                        <label class="form-check-label" for="perm-{{ $key }}">{{ $label }}</label>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
              @endforeach
            </div>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mb-4">
          <a href="{{ route('dispatcher.users.index') }}" class="btn btn-label-secondary order-2 order-sm-1">Cancel</a>
          <button type="submit" class="btn btn-primary order-1 order-sm-2">
            <i class="ti tabler-device-floppy me-1"></i>{{ $editing ? 'Save Changes' : 'Create Role' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  (function () {
    const checks = Array.from(document.querySelectorAll('.perm-check'));

    document.getElementById('selectAll')?.addEventListener('click', () => checks.forEach(c => c.checked = true));
    document.getElementById('clearAll')?.addEventListener('click', () => checks.forEach(c => c.checked = false));

    document.querySelectorAll('.group-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        const inGroup = checks.filter(c => c.dataset.group === button.dataset.group);
        const allOn = inGroup.every(c => c.checked);
        inGroup.forEach(c => c.checked = !allOn);
      });
    });
  })();
</script>
@endsection
