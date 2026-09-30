@extends('layouts/layoutMaster')

@php
  $selected = old('features', $plan->featureList());
@endphp

@section('title', $isEdit ? 'Edit Subscription Plan' : 'Create Subscription Plan')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="row">
    <div class="col-xl-9 col-12 mx-auto">

      <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.subscription') }}" class="btn btn-icon btn-label-secondary me-3" aria-label="Back">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <div class="min-w-0">
          <h3 class="mb-0 fw-bold text-heading">{{ $isEdit ? 'Edit Subscription Plan' : 'Create Subscription Plan' }}</h3>
          <small class="text-muted">What this tier costs, how much it allows, and which screens it unlocks.</small>
        </div>
      </div>

      <form method="POST"
            action="{{ $isEdit ? route('admin.subscription.update', $plan->id) : route('admin.subscription.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- Plan details --}}
        <div class="card mb-4">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0 fw-semibold text-heading">
              <i class="ti tabler-file-dollar me-2 text-primary"></i>Plan Details
            </h5>
          </div>
          <div class="card-body pt-4">
            <div class="row g-4">
              <div class="col-12">
                <label class="form-label fw-semibold" for="name">Plan Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" required
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="e.g. Basic Tier" value="{{ old('name', $plan->name) }}" />
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="price">Display Price <span class="text-danger">*</span></label>
                <input type="text" id="price" name="price" required
                       class="form-control @error('price') is-invalid @enderror"
                       placeholder="$299 or Custom" value="{{ old('price', $plan->price) }}" />
                <small class="text-muted">What the customer reads.</small>
                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="price_amount">Amount to charge</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" min="0" id="price_amount" name="price_amount"
                         class="form-control @error('price_amount') is-invalid @enderror"
                         placeholder="299.00" value="{{ old('price_amount', $plan->price_amount) }}" />
                </div>
                <small class="text-muted">Leave empty for a "contact us" tier.</small>
                @error('price_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="billing_interval">Billing period <span class="text-danger">*</span></label>
                <select id="billing_interval" name="billing_interval" class="form-select" required>
                  @foreach(\App\Models\SubscriptionPlan::intervals() as $key => $meta)
                    <option value="{{ $key }}"
                      @selected(old('billing_interval', $plan->billing_interval ?? 'month') === $key)>
                      {{ $meta['label'] }} ({{ $meta['suffix'] }})
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">How often the customer is charged.</small>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold" for="description">Description <span class="text-danger">*</span></label>
                <textarea id="description" name="description" rows="2" required maxlength="500"
                          class="form-control @error('description') is-invalid @enderror"
                          placeholder="Up to 10 vehicles. Core dispatching features.">{{ old('description', $plan->description) }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 col-sm-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                         @checked(old('is_active', $plan->is_active ?? true)) />
                  <label class="form-check-label" for="is_active">
                    Available to buy
                    <small class="d-block text-muted">Unticked, it stays assignable by you but customers cannot choose it.</small>
                  </label>
                </div>
              </div>

              <div class="col-12 col-sm-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1"
                         @checked(old('is_featured', $plan->is_featured)) />
                  <label class="form-check-label" for="is_featured">
                    Mark as popular
                    <small class="d-block text-muted">Highlights the tier on the customer's plan list.</small>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Limits --}}
        <div class="card mb-4">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0 fw-semibold text-heading">
              <i class="ti tabler-gauge me-2 text-primary"></i>Limits
            </h5>
            <small class="text-muted">Leave a field empty for unlimited. These are what the usage bars measure against.</small>
          </div>
          <div class="card-body pt-4">
            <div class="row g-4">
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="vehicle_limit">Vehicles</label>
                <input type="number" min="1" id="vehicle_limit" name="vehicle_limit"
                       class="form-control @error('vehicle_limit') is-invalid @enderror"
                       placeholder="Unlimited" value="{{ old('vehicle_limit', $plan->vehicle_limit) }}" />
                @error('vehicle_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="driver_limit">Drivers</label>
                <input type="number" min="1" id="driver_limit" name="driver_limit"
                       class="form-control @error('driver_limit') is-invalid @enderror"
                       placeholder="Unlimited" value="{{ old('driver_limit', $plan->driver_limit) }}" />
                @error('driver_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="trip_limit">Trips per month</label>
                <input type="number" min="1" id="trip_limit" name="trip_limit"
                       class="form-control @error('trip_limit') is-invalid @enderror"
                       placeholder="Unlimited" value="{{ old('trip_limit', $plan->trip_limit) }}" />
                @error('trip_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>
        </div>

        {{-- Features --}}
        <div class="card mb-4">
          <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h5 class="card-title mb-0 fw-semibold text-heading">
                <i class="ti tabler-checklist me-2 text-primary"></i>What this tier unlocks
              </h5>
              <small class="text-muted">
                A company on this plan only sees these screens, whatever their staff's roles allow.
              </small>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-label-secondary" id="selectAll">Select all</button>
              <button type="button" class="btn btn-sm btn-label-secondary" id="clearAll">Clear optional</button>
            </div>
          </div>
          <div class="card-body pt-4">
            <div class="row g-4">
              @foreach($groups as $groupName => $features)
              <div class="col-12 col-lg-6">
                <div class="border rounded h-100">
                  <div class="px-3 py-2 border-bottom bg-lighter">
                    <h6 class="mb-0 fw-semibold">{{ $groupName }}</h6>
                  </div>
                  <div class="p-3 d-flex flex-column gap-3">
                    @foreach($features as $key => $meta)
                      @php($locked = in_array($key, \App\Support\PlanFeatures::mandatory(), true))
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input feature-check" type="checkbox"
                               name="features[]" value="{{ $key }}" id="feature-{{ $key }}"
                               @checked($locked || in_array($key, $selected, true))
                               @disabled($locked)
                               data-locked="{{ $locked ? '1' : '0' }}" />
                        <label class="form-check-label" for="feature-{{ $key }}">
                          {{ $meta['label'] }}
                          @if($locked)
                            <span class="badge bg-label-secondary ms-1">Always on</span>
                          @endif
                          <small class="d-block text-muted">{{ $meta['help'] }}</small>
                        </label>
                      </div>
                      @if($locked)
                        {{-- A disabled checkbox posts nothing, so the core
                             features are sent alongside it. --}}
                        <input type="hidden" name="features[]" value="{{ $key }}" />
                      @endif
                    @endforeach
                  </div>
                </div>
              </div>
              @endforeach
            </div>
          </div>
        </div>

        {{-- Stripe. Nothing to fill in: saving the plan creates or updates
             the product and price through the secret key. --}}
        <div class="card mb-4">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0 fw-semibold text-heading">
              <i class="ti tabler-credit-card me-2 text-primary"></i>Stripe
            </h5>
          </div>
          <div class="card-body pt-4">
            @if(! config('services.stripe.secret'))
              <div class="alert alert-warning mb-0" role="alert">
                <i class="ti tabler-alert-triangle me-2"></i>
                Stripe keys are not configured, so this tier cannot be sold.
                You can still assign it to a company by hand.
              </div>
            @elseif($plan->exists && $plan->stripe_price_id)
              <div class="d-flex align-items-start gap-2 mb-3">
                <i class="ti tabler-circle-check text-success mt-1 flex-shrink-0"></i>
                <div class="min-w-0">
                  <span class="d-block fw-semibold">Live in Stripe</span>
                  <small class="text-muted d-block text-break">Price: {{ $plan->stripe_price_id }}</small>
                  @if($plan->stripe_product_id)
                    <small class="text-muted d-block text-break">Product: {{ $plan->stripe_product_id }}</small>
                  @endif
                </div>
              </div>
              <p class="text-muted small mb-0">
                Changing the amount or the billing period creates a new price when you save.
                Companies already subscribed keep paying the price they signed up on.
              </p>
            @else
              <div class="d-flex align-items-start gap-2">
                <i class="ti tabler-cloud-upload text-primary mt-1 flex-shrink-0"></i>
                <div>
                  <span class="d-block fw-semibold">Created automatically</span>
                  <small class="text-muted">
                    Saving this plan with an amount creates the product and recurring price in
                    Stripe for you. Leave the amount empty for a "contact us" tier.
                  </small>
                </div>
              </div>
            @endif
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mb-4">
          <a href="{{ route('admin.subscription') }}" class="btn btn-label-secondary order-2 order-sm-1">Cancel</a>
          <button type="submit" class="btn btn-primary order-1 order-sm-2">
            <i class="ti tabler-device-floppy me-1"></i>{{ $isEdit ? 'Save Plan' : 'Create Plan' }}
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
    // The always-on features are disabled, so the buttons must leave them be.
    const optional = Array.from(document.querySelectorAll('.feature-check'))
      .filter(c => c.dataset.locked !== '1');

    document.getElementById('selectAll')?.addEventListener('click', function () {
      optional.forEach(c => { c.checked = true; });
    });

    document.getElementById('clearAll')?.addEventListener('click', function () {
      optional.forEach(c => { c.checked = false; });
    });
  })();
</script>
@endsection
