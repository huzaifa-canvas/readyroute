@extends('layouts/layoutMaster')

@section('title', 'Company Organizations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Alert Messages --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
      <i class="ti tabler-circle-check me-2"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
      <i class="ti tabler-alert-circle me-2"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Page Header Card --}}
  <div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
      <h4 class="fw-bold text-heading mb-0">Company Organizations</h4>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <form method="GET" action="{{ route('admin.company.list') }}" class="d-flex">
          <div class="input-group input-group-merge" style="min-width: 220px;">
            <span class="input-group-text"><i class="ti tabler-search"></i></span>
            <input
              type="text"
              name="search"
              class="form-control"
              placeholder="Search companies.."
              value="{{ request('search') }}" />
          </div>
        </form>
        <a href="{{ route('admin.company.create') }}" class="btn btn-primary">
          <i class="ti tabler-plus me-1"></i> Register Company
        </a>
      </div>
    </div>
  </div>

  {{-- Company Table Card --}}
  <div class="card">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Company Name</th>
            <th>Region</th>
            <th>Drivers</th>
            <th>Vehicles</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($companies as $company)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-sm">
                  <img src="{{ $company->avatar_url }}" alt="{{ $company->name }}" class="rounded-circle" />
                </div>
                <div>
                  <span class="fw-bold text-heading d-block">{{ $company->name }}</span>
                  <small class="text-muted">{{ $company->email }}</small>
                </div>
              </div>
            </td>
            <td>
              <span class="text-body">{{ $company->getMeta('region', '—') }}</span>
            </td>
            <td>
              <span class="badge bg-label-primary px-3 py-2 fw-semibold">
                <i class="ti tabler-steering-wheel me-1"></i>
                {{ $company->drivers_count ?? 0 }}
              </span>
            </td>
            <td>
              <span class="badge bg-label-success px-3 py-2 fw-semibold">
                <i class="ti tabler-car me-1"></i>
                {{ $company->vehicles_count ?? 0 }}
              </span>
            </td>
            <td class="text-center">
              <div class="dropdown">
                <button class="btn btn-icon btn-label-secondary rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="ti tabler-dots-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                  <li>
                    <a class="dropdown-item" href="{{ route('admin.user.edit', $company->id) }}">
                      <i class="ti tabler-settings me-2"></i>Manage
                    </a>
                  </li>
                  <li>
                    <hr class="dropdown-divider" />
                  </li>
                  <li>
                    <form action="{{ route('admin.company.delete', $company->id) }}" method="POST">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to remove this company?')">
                        <i class="ti tabler-trash me-2"></i>Remove
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="ti tabler-building-community fs-1 d-block mb-2 text-secondary"></i>
              No companies found. Click "+ Register Company" to add one.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($companies->hasPages())
    <div class="card-footer d-flex justify-content-center py-3 border-top">
      {{ $companies->appends(request()->query())->links() }}
    </div>
    @endif
  </div>

</div>
@endsection
