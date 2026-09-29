@extends('layouts/layoutMaster')

@section('title', 'Archived Companies')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Archived Companies</h4>
      <p class="text-muted mb-0 small">
        Removed accounts. Their trips, drivers and clients are still stored and come back on restore.
      </p>
    </div>
  </div>

  <ul class="nav nav-pills flex-column flex-sm-row mb-4">
    <li class="nav-item">
      <a class="nav-link" href="{{ route('admin.company.list') }}">
        <i class="ti tabler-building me-1"></i> Companies
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link active" href="{{ route('admin.company.archived') }}">
        <i class="ti tabler-archive me-1"></i> Archived
        <span class="badge bg-label-secondary ms-1">{{ $companies->total() }}</span>
      </a>
    </li>
  </ul>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Company</th>
            <th class="d-none d-md-table-cell">Archived</th>
            <th class="d-none d-lg-table-cell text-center">Drivers</th>
            <th class="d-none d-lg-table-cell text-center">Vehicles</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($companies as $company)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-sm flex-shrink-0">
                  <img src="{{ $company->avatar_url }}" alt="{{ $company->name }}" class="rounded-circle" />
                </div>
                <div class="min-w-0">
                  <a href="{{ route('admin.company.show', $company->id) }}"
                     class="fw-bold text-heading d-block text-truncate">{{ $company->name }}</a>
                  <small class="text-muted d-block text-truncate">{{ $company->email }}</small>
                  <small class="text-muted d-md-none">
                    Archived {{ $company->deleted_at?->diffForHumans() }}
                  </small>
                </div>
              </div>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="text-body">{{ $company->deleted_at?->format('d M Y') }}</span>
              <small class="text-muted d-block">{{ $company->deleted_at?->diffForHumans() }}</small>
            </td>
            <td class="d-none d-lg-table-cell text-center">
              <span class="badge bg-label-secondary px-3 py-2">{{ $company->drivers_count ?? 0 }}</span>
            </td>
            <td class="d-none d-lg-table-cell text-center">
              <span class="badge bg-label-secondary px-3 py-2">{{ $company->vehicles_count ?? 0 }}</span>
            </td>
            <td class="text-end">
              <div class="d-flex gap-2 justify-content-end">
                <form action="{{ route('admin.company.restore', $company->id) }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-label-primary">
                    <i class="ti tabler-rotate me-1"></i>Restore
                  </button>
                </form>
                <button type="button" class="btn btn-sm btn-label-danger"
                        data-bs-toggle="modal" data-bs-target="#purgeModal{{ $company->id }}">
                  <i class="ti tabler-trash"></i>
                  <span class="d-none d-sm-inline ms-1">Delete</span>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="ti tabler-archive fs-1 d-block mb-2 text-secondary"></i>
              Nothing is archived.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($companies->hasPages())
    <div class="card-footer d-flex justify-content-center py-3 border-top">
      {{ $companies->links() }}
    </div>
    @endif
  </div>
</div>

{{-- Permanent-delete confirmations. Typing the name is the only guard against
     destroying a tenant and everything under it. --}}
@foreach($companies as $company)
<div class="modal fade" id="purgeModal{{ $company->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" action="{{ route('admin.company.force-delete', $company->id) }}" method="POST">
      @csrf
      @method('DELETE')
      <div class="modal-header">
        <h5 class="modal-title text-danger">Delete permanently</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger" role="alert">
          <i class="ti tabler-alert-triangle me-2"></i>
          This cannot be undone. Every trip, driver, vehicle and client belonging to this
          company is deleted with it.
        </div>
        <label class="form-label" for="confirm{{ $company->id }}">
          Type <strong>{{ $company->name }}</strong> to confirm
        </label>
        <input type="text" id="confirm{{ $company->id }}" name="confirm_name" class="form-control"
               autocomplete="off" placeholder="{{ $company->name }}" required />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete permanently</button>
      </div>
    </form>
  </div>
</div>
@endforeach
@endsection
