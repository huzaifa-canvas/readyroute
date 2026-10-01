@extends('layouts/layoutMaster')

@section('title', 'Register Company')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Error Alerts --}}
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
      <ul class="mb-0 ps-3 small">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="row">
    <div class="col-xl-7 col-lg-9 col-md-12 mx-auto">
      <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.company.list') }}" class="btn btn-icon btn-label-secondary me-3">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <h3 class="mb-0 fw-bold text-heading">Register Company</h3>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom">
          <h5 class="card-title mb-0 fw-semibold text-heading"><i class="ti tabler-building me-2 text-primary"></i>Company Details</h5>
        </div>
        <div class="card-body pt-4">
          <form method="POST" action="{{ route('admin.company.store') }}">
            @csrf

            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-semibold" for="name">Company Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. ABC Transit LLC" value="{{ old('name') }}" required />
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold" for="email">Email Address <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="company@example.com" value="{{ old('email') }}" required />
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold" for="phone_number">Phone Number</label>
                <input type="text" id="phone_number" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror" placeholder="+1 (555) 000-0000" value="{{ old('phone_number') }}" />
                @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-12">
                <label class="form-label fw-semibold" for="region">Region</label>
                <input type="text" id="region" name="region" class="form-control @error('region') is-invalid @enderror" placeholder="e.g. Dallas, TX" value="{{ old('region') }}" />
                @error('region') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold" for="password">Password <span class="text-danger">*</span></label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Minimum 8 characters" required minlength="8" />
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold" for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Re-enter password" required />
              </div>
            </div>

            {{-- Free access.
                 Optional: leave both fields alone and the company registers
                 with no plan, which means it can sign in and look around but
                 cannot create anything until it subscribes. Filling them in
                 puts the company on a plan without charging it, up to the
                 date given. --}}
            <hr class="my-4">

            <div class="d-flex align-items-center mb-3">
              <h6 class="mb-0 fw-semibold text-heading">
                <i class="ti tabler-gift me-2 text-primary"></i>Free Access
              </h6>
              <span class="badge bg-label-secondary ms-2">Optional</span>
            </div>

            <p class="text-muted small mb-3">
              Let this company use a plan without paying, until a date you choose. When that
              date passes they keep their data and can still sign in, but cannot create
              anything until they subscribe — or until you extend the date.
            </p>

            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="subscription_plan_id">Plan</label>
                <select id="subscription_plan_id" name="subscription_plan_id"
                        class="form-select @error('subscription_plan_id') is-invalid @enderror">
                  <option value="">No plan — they subscribe themselves</option>
                  @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" {{ old('subscription_plan_id') == $plan->id ? 'selected' : '' }}>
                      {{ $plan->name }} ({{ $plan->price }}{{ $plan->billing_period ? ' / ' . $plan->billing_period : '' }})
                    </option>
                  @endforeach
                </select>
                @error('subscription_plan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold" for="free_until">Free until</label>
                <input type="date" id="free_until" name="free_until"
                       class="form-control @error('free_until') is-invalid @enderror"
                       min="{{ now()->addDay()->toDateString() }}"
                       value="{{ old('free_until') }}" />
                @error('free_until') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="text-muted">Access ends at the end of this day.</small>
              </div>
            </div>

            <div class="mt-4 pt-2">
              <button type="submit" class="btn btn-primary px-4 me-2">Register Company</button>
              <a href="{{ route('admin.company.list') }}" class="btn btn-label-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
