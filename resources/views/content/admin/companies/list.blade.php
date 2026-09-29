@extends('layouts/layoutMaster')

@section('title', 'Company Organizations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  {{-- Page header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Company Organizations</h4>
      <p class="text-muted mb-0 small">Every transportation company on the platform.</p>
    </div>
    <a href="{{ route('admin.company.create') }}" class="btn btn-primary">
      <i class="ti tabler-plus me-1"></i> Register Company
    </a>
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.company.list') }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-lg-5">
          <label class="form-label" for="search">Search</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ti tabler-search"></i></span>
            <input type="text" id="search" name="search" class="form-control"
                   placeholder="Company name or email" value="{{ request('search') }}" />
          </div>
        </div>
        <div class="col-8 col-md-4 col-lg-3">
          <label class="form-label" for="status">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">All</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
          </select>
        </div>
        <div class="col-4 col-md-2 col-lg-2">
          <button type="submit" class="btn btn-label-primary w-100">Filter</button>
        </div>
        @if(request('search') || request('status'))
          <div class="col-12">
            <a href="{{ route('admin.company.list') }}" class="text-muted small">
              <i class="ti tabler-x icon-xs me-1"></i>Clear filters
            </a>
          </div>
        @endif
      </form>
    </div>
  </div>

  {{-- Tabs: active list vs archived --}}
  <ul class="nav nav-pills flex-column flex-sm-row mb-4">
    <li class="nav-item">
      <a class="nav-link active" href="{{ route('admin.company.list') }}">
        <i class="ti tabler-building me-1"></i> Companies
        <span class="badge bg-label-primary ms-1">{{ $companies->total() }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="{{ route('admin.company.archived') }}">
        <i class="ti tabler-archive me-1"></i> Archived
        @if($archivedCount > 0)
          <span class="badge bg-label-secondary ms-1">{{ $archivedCount }}</span>
        @endif
      </a>
    </li>
  </ul>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Company</th>
            <th class="d-none d-md-table-cell">Region</th>
            <th class="d-none d-sm-table-cell">Status</th>
            <th class="d-none d-lg-table-cell text-center">Drivers</th>
            <th class="d-none d-lg-table-cell text-center">Vehicles</th>
            <th class="d-none d-xl-table-cell text-center">Clients</th>
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

                  {{-- Everything hidden from the narrow table, folded in here --}}
                  <div class="d-flex flex-wrap gap-1 mt-1 d-sm-none">
                    <span class="badge {{ $company->statusClass() }}">{{ $company->statusLabel() }}</span>
                    <span class="badge bg-label-secondary">{{ $company->drivers_count ?? 0 }} drivers</span>
                    <span class="badge bg-label-secondary">{{ $company->vehicles_count ?? 0 }} vehicles</span>
                  </div>
                </div>
              </div>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="text-body">{{ $company->getMeta('region') ?: '—' }}</span>
            </td>
            <td class="d-none d-sm-table-cell">
              <span class="badge {{ $company->statusClass() }}">{{ $company->statusLabel() }}</span>
            </td>
            <td class="d-none d-lg-table-cell text-center">
              <span class="badge bg-label-primary px-3 py-2 fw-semibold">{{ $company->drivers_count ?? 0 }}</span>
            </td>
            <td class="d-none d-lg-table-cell text-center">
              <span class="badge bg-label-success px-3 py-2 fw-semibold">{{ $company->vehicles_count ?? 0 }}</span>
            </td>
            <td class="d-none d-xl-table-cell text-center">
              <span class="badge bg-label-info px-3 py-2 fw-semibold">{{ $company->clients_count ?? 0 }}</span>
            </td>
            <td class="text-end">
              <div class="dropdown">
                <button class="btn btn-icon btn-label-secondary rounded-circle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $company->name }}">
                  <i class="ti tabler-dots-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                  <li>
                    <a class="dropdown-item" href="{{ route('admin.company.show', $company->id) }}">
                      <i class="ti tabler-eye me-2"></i>Manage
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="{{ route('admin.company.edit', $company->id) }}">
                      <i class="ti tabler-edit me-2"></i>Edit details
                    </a>
                  </li>
                  <li><hr class="dropdown-divider" /></li>
                  @if($company->isSuspended())
                    <li>
                      <form action="{{ route('admin.company.activate', $company->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item text-success">
                          <i class="ti tabler-player-play me-2"></i>Reactivate
                        </button>
                      </form>
                    </li>
                  @else
                    <li>
                      <button type="button" class="dropdown-item text-warning"
                              data-bs-toggle="modal" data-bs-target="#suspendModal{{ $company->id }}">
                        <i class="ti tabler-ban me-2"></i>Suspend
                      </button>
                    </li>
                  @endif
                  <li>
                    <form action="{{ route('admin.company.delete', $company->id) }}" method="POST">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="dropdown-item text-danger"
                              onclick="return confirm('Archive {{ $company->name }}? Nothing is deleted — you can restore it from the archived list.')">
                        <i class="ti tabler-archive me-2"></i>Archive
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="ti tabler-building-community fs-1 d-block mb-2 text-secondary"></i>
              No companies found.
              <div class="mt-3">
                <a href="{{ route('admin.company.create') }}" class="btn btn-primary btn-sm">Register Company</a>
              </div>
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

{{-- Suspend modals, kept outside the table so the dropdown can close first --}}
@foreach($companies as $company)
  @if(! $company->isSuspended())
  <div class="modal fade" id="suspendModal{{ $company->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" action="{{ route('admin.company.suspend', $company->id) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Suspend {{ $company->name }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted">
            The company keeps all its data but is signed out and cannot log in until reactivated.
          </p>
          <label class="form-label" for="reason{{ $company->id }}">Reason (optional)</label>
          <input type="text" id="reason{{ $company->id }}" name="suspension_reason" class="form-control"
                 maxlength="255" placeholder="e.g. Payment overdue" />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Suspend</button>
        </div>
      </form>
    </div>
  </div>
  @endif
@endforeach
@endsection
