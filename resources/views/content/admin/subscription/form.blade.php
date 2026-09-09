@extends('layouts/layoutMaster')

@section('title', $isEdit ? 'Edit Subscription Plan' : 'Create Subscription Plan')

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
        <a href="{{ route('admin.subscription') }}" class="btn btn-icon btn-label-secondary me-3">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <h3 class="mb-0 fw-bold text-heading">{{ $isEdit ? 'Edit Subscription Plan' : 'Create Subscription Plan' }}</h3>
      </div>

      <div class="card mb-4 border">
        <div class="card-header border-bottom">
          <h5 class="card-title mb-0 fw-semibold text-heading">
            <i class="ti tabler-file-dollar me-2 text-primary"></i>Plan Information
          </h5>
        </div>
        <div class="card-body pt-4">
          <form method="POST" action="{{ $isEdit ? route('admin.subscription.update', $plan->id) : route('admin.subscription.store') }}">
            @csrf
            @if($isEdit)
              @method('PUT')
            @endif

            <div class="row g-4">
              {{-- Plan Name --}}
              <div class="col-md-12">
                <label class="form-label fw-semibold" for="name">Plan Name <span class="text-danger">*</span></label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  class="form-control @error('name') is-invalid @enderror"
                  placeholder="e.g. Basic Tier, Professional Tier"
                  value="{{ old('name', $plan->name) }}"
                  required />
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Price --}}
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="price">Price <span class="text-danger">*</span></label>
                <input
                  type="text"
                  id="price"
                  name="price"
                  class="form-control @error('price') is-invalid @enderror"
                  placeholder="e.g. $299 or Custom"
                  value="{{ old('price', $plan->price) }}"
                  required />
                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Billing Period --}}
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="billing_period">Billing Period</label>
                <select id="billing_period" name="billing_period" class="form-select @error('billing_period') is-invalid @enderror">
                  <option value="/mo" {{ old('billing_period', $plan->billing_period) == '/mo' ? 'selected' : '' }}>Monthly (/mo)</option>
                  <option value="/yr" {{ old('billing_period', $plan->billing_period) == '/yr' ? 'selected' : '' }}>Yearly (/yr)</option>
                  <option value="" {{ old('billing_period', $plan->billing_period) == '' ? 'selected' : '' }}>None / Custom</option>
                </select>
                @error('billing_period') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              {{-- Description --}}
              <div class="col-md-12">
                <label class="form-label fw-semibold" for="description">Plan Description <span class="text-danger">*</span></label>
                <textarea
                  id="description"
                  name="description"
                  rows="3"
                  class="form-control @error('description') is-invalid @enderror"
                  placeholder="e.g. Up to 10 vehicles. Core dispatching features."
                  required>{{ old('description', $plan->description) }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="mt-4 pt-2">
              <button type="submit" class="btn btn-primary px-4 me-2">
                {{ $isEdit ? 'Update Plan' : 'Create Plan' }}
              </button>
              <a href="{{ route('admin.subscription') }}" class="btn btn-label-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
