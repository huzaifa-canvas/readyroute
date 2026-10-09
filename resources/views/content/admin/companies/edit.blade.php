@extends('layouts/layoutMaster')

@section('title', 'Edit — ' . $company->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="row">
    <div class="col-xl-7 col-lg-9 col-md-12 mx-auto">

      <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.company.show', $company->id) }}" class="btn btn-icon btn-label-secondary me-3" aria-label="Back">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <div class="min-w-0">
          <h3 class="mb-0 fw-bold text-heading text-break">Edit Company</h3>
          <small class="text-muted">{{ $company->name }}</small>
        </div>
      </div>

      <form method="POST" action="{{ route('admin.company.update', $company->id) }}">
        @csrf
        @method('PUT')

        <div class="card mb-4">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0 fw-semibold text-heading">
              <i class="ti tabler-building me-2 text-primary"></i>Company Details
            </h5>
          </div>
          <div class="card-body pt-4">
            <div class="row g-4">
              <div class="col-12">
                <label class="form-label fw-semibold" for="name">Company Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name"
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $company->name) }}" required />
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="email">Email Address <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $company->email) }}" required />
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="phone_number">Phone Number</label>
                <input type="text" id="phone_number" name="phone_number"
                       class="form-control @error('phone_number') is-invalid @enderror"
                       value="{{ old('phone_number', $company->phone_number) }}" />
                @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold" for="region">Region</label>
                <input type="text" id="region" name="region"
                       class="form-control @error('region') is-invalid @enderror"
                       placeholder="e.g. Dallas, TX"
                       value="{{ old('region', $company->getMeta('region')) }}" />
                @error('region') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0 fw-semibold text-heading">
              <i class="ti tabler-key me-2 text-primary"></i>Reset Password
            </h5>
          </div>
          <div class="card-body pt-4">
            <p class="text-muted small mb-4">
              Leave both fields empty to keep the current password unchanged.
            </p>
            <div class="row g-4">
              <div class="col-12 col-md-6 form-password-toggle">
                <label class="form-label fw-semibold" for="password">New Password</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="password" name="password" autocomplete="new-password"
                         class="form-control @error('password') is-invalid @enderror"
                         placeholder="At least 8 characters" />
                  <span class="input-group-text cursor-pointer" role="button" tabindex="0"
                        aria-label="Show or hide the password">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-md-6 form-password-toggle">
                <label class="form-label fw-semibold" for="password_confirmation">Confirm New Password</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="password_confirmation" name="password_confirmation"
                         autocomplete="new-password" class="form-control" />
                  <span class="input-group-text cursor-pointer" role="button" tabindex="0"
                        aria-label="Show or hide the password">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mb-4">
          <a href="{{ route('admin.company.show', $company->id) }}" class="btn btn-label-secondary order-2 order-sm-1">Cancel</a>
          <button type="submit" class="btn btn-primary order-1 order-sm-2">
            <i class="ti tabler-device-floppy me-1"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
@include('_partials._password-toggle')
@endsection
