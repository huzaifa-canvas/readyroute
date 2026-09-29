@extends('layouts/layoutMaster')

@section('title', 'My Subscription')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php
    $plan    = $company->subscriptionPlan;
    $isOwner = auth()->user()->isCompanyOwner() || auth()->user()->isAdmin();
    $labels  = ['vehicle' => 'Vehicles', 'driver' => 'Drivers', 'trip' => 'Trips this month'];
  @endphp

  <div class="mb-4">
    <h4 class="fw-bold text-heading mb-1">My Subscription</h4>
    <p class="text-muted mb-0 small">Your plan, what you are using, and what else is available.</p>
  </div>

  @if(count($exceeded) > 0)
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
      <i class="ti tabler-alert-triangle flex-shrink-0"></i>
      <span>
        You are over the plan limit for
        <strong>{{ implode(', ', array_map(fn ($k) => strtolower($labels[$k]), $exceeded)) }}</strong>.
        Consider moving to a larger tier.
      </span>
    </div>
  @endif

  <div class="row g-4">

    {{-- Current plan --}}
    <div class="col-12 col-xl-5">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Current plan</h5>
        </div>
        <div class="card-body">
          @if($plan)
            <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
              <h3 class="fw-bold mb-0">{{ $plan->name }}</h3>
              @if($plan->is_featured)
                <span class="badge bg-label-primary">Popular</span>
              @endif
            </div>
            <p class="text-muted mb-3">{{ $plan->description }}</p>

            <h4 class="fw-bold mb-3" style="font-variant-numeric: tabular-nums;">{{ $plan->priceLabel() }}</h4>

            <dl class="row mb-0">
              <dt class="col-6 text-muted fw-normal small py-1">Started</dt>
              <dd class="col-6 py-1">{{ $company->subscribed_at?->format('d M Y') ?? '—' }}</dd>

              <dt class="col-6 text-muted fw-normal small py-1">Next billing date</dt>
              <dd class="col-6 py-1">
                {{ $company->renews_at?->format('d M Y') ?? '—' }}
                @if($company->renews_at)
                  <small class="text-muted d-block">{{ $company->renews_at->diffForHumans() }}</small>
                @endif
              </dd>
            </dl>
          @else
            <div class="text-center py-4 text-muted">
              <i class="ti tabler-credit-card-off fs-2 d-block mb-2 text-secondary"></i>
              <h6>No plan selected</h6>
              <p class="mb-0 small">Choose one below to get started.</p>
            </div>
          @endif
        </div>

        <div class="card-footer">
          {{-- Said plainly: no provider is wired up, so nothing is charged. --}}
          <div class="d-flex align-items-start gap-2 text-muted small">
            <i class="ti tabler-info-circle flex-shrink-0 mt-1"></i>
            <span>
              Payment collection is not connected yet. Changing plan takes effect immediately
              and no card is charged.
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Usage --}}
    <div class="col-12 col-xl-7">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Usage</h5>
          <small class="text-muted">Against your plan's limits.</small>
        </div>
        <div class="card-body">
          @foreach($usage as $resource => $row)
            @php($over = $row['limit'] !== null && $row['used'] > $row['limit'])
            <div class="{{ ! $loop->last ? 'mb-4' : '' }}">
              <div class="d-flex justify-content-between align-items-baseline mb-1">
                <span class="fw-semibold">{{ $labels[$resource] }}</span>
                <span class="{{ $over ? 'text-danger fw-semibold' : 'text-muted' }}"
                      style="font-variant-numeric: tabular-nums;">
                  {{ number_format($row['used']) }} /
                  {{ $row['limit'] === null ? 'Unlimited' : number_format($row['limit']) }}
                </span>
              </div>

              @if($row['percent'] === null)
                <div class="progress" style="height: 6px;">
                  <div class="progress-bar bg-label-secondary" style="width: 100%; opacity: .35;"></div>
                </div>
                <small class="text-muted">No cap on this plan</small>
              @else
                <div class="progress" style="height: 6px;" role="progressbar"
                     aria-label="{{ $labels[$resource] }} usage"
                     aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                  <div class="progress-bar {{ $over ? 'bg-danger' : ($row['percent'] >= 80 ? 'bg-warning' : 'bg-primary') }}"
                       style="width: {{ $row['percent'] }}%"></div>
                </div>
                <small class="{{ $over ? 'text-danger' : 'text-muted' }}">
                  {{ $over ? 'Over the limit' : $row['percent'] . '% used' }}
                </small>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Plans --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Available plans</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            @foreach($plans as $option)
              @php($current = $plan && $plan->id === $option->id)
              <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 {{ $current ? 'border-primary' : '' }}">
                  <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                      <h5 class="mb-0">{{ $option->name }}</h5>
                      @if($current)
                        <span class="badge bg-primary flex-shrink-0">Current</span>
                      @elseif($option->is_featured)
                        <span class="badge bg-label-primary flex-shrink-0">Popular</span>
                      @endif
                    </div>

                    <h4 class="fw-bold mb-3" style="font-variant-numeric: tabular-nums;">
                      {{ $option->priceLabel() }}
                    </h4>

                    <p class="text-muted small flex-grow-1">{{ $option->description }}</p>

                    <ul class="list-unstyled d-grid gap-2 mb-4 small">
                      <li><i class="ti tabler-car icon-xs me-2 text-primary"></i>{{ $option->limitLabel('vehicle') }} vehicles</li>
                      <li><i class="ti tabler-steering-wheel icon-xs me-2 text-primary"></i>{{ $option->limitLabel('driver') }} drivers</li>
                      <li><i class="ti tabler-route icon-xs me-2 text-primary"></i>{{ $option->limitLabel('trip') }} trips / month</li>
                    </ul>

                    @if($current)
                      <button type="button" class="btn btn-label-primary w-100" disabled>Current plan</button>
                    @elseif($isOwner)
                      <form method="POST" action="{{ route('dispatcher.subscription.change') }}">
                        @csrf
                        <input type="hidden" name="subscription_plan_id" value="{{ $option->id }}" />
                        <button type="submit" class="btn btn-primary w-100"
                                onclick="return confirm('Switch to the {{ $option->name }}?')">
                          Switch to this plan
                        </button>
                      </form>
                    @else
                      <button type="button" class="btn btn-label-secondary w-100" disabled
                              title="Only the company account can change the plan">
                        Company account only
                      </button>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
