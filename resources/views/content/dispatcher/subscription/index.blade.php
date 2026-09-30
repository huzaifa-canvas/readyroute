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

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">My Subscription</h4>
      <p class="text-muted mb-0 small">Your plan, what you are using, and what else is available.</p>
    </div>
    <span class="badge {{ auth()->user()->subscriptionStatusClass() }} px-3 py-2">
      {{ auth()->user()->subscriptionStatusLabel() }}
    </span>
  </div>

  @unless(auth()->user()->hasActiveSubscription())
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
      <i class="ti tabler-lock flex-shrink-0 mt-1"></i>
      <div>
        <strong>The panel is read-only.</strong>
        You can sign in and look at everything, but nothing can be created or changed
        until a plan is active. Choose one below to start working again.
      </div>
    </div>
  @endunless

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

        <div class="card-footer d-flex flex-column gap-3">
          <div class="d-flex align-items-start gap-2 text-muted small">
            <i class="ti tabler-info-circle flex-shrink-0 mt-1"></i>
            <span>
              @if($canPay)
                Payment is taken by Stripe without leaving this page.
                Card details never reach our servers.
              @else
                Card payments are not configured on this platform yet.
                Your administrator can assign a plan for you.
              @endif
            </span>
          </div>

          @if($company->subscription_status === 'active' && (auth()->user()->isCompanyOwner() || auth()->user()->isAdmin()))
            <form method="POST" action="{{ route('dispatcher.subscription.cancel') }}">
              @csrf
              <button type="submit" class="btn btn-text-danger btn-sm p-0"
                      onclick="return confirm('Cancel the subscription? The panel becomes read-only.')">
                Cancel subscription
              </button>
            </form>
          @endif
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

                    <ul class="list-unstyled d-grid gap-2 mb-3 small">
                      <li><i class="ti tabler-car icon-xs me-2 text-primary"></i>{{ $option->limitLabel('vehicle') }} vehicles</li>
                      <li><i class="ti tabler-steering-wheel icon-xs me-2 text-primary"></i>{{ $option->limitLabel('driver') }} drivers</li>
                      <li><i class="ti tabler-route icon-xs me-2 text-primary"></i>{{ $option->limitLabel('trip') }} trips / month</li>
                    </ul>

                    {{-- What the tier unlocks. Only the optional features are
                         listed, because every plan can run trips and saying so
                         on each card is noise. --}}
                    @php($optional = array_values(array_diff($option->featureList(), \App\Support\PlanFeatures::mandatory())))
                    @if(count($optional) > 0)
                      <ul class="list-unstyled d-grid gap-1 mb-4 small">
                        @foreach($optional as $feature)
                          <li class="text-muted">
                            <i class="ti tabler-check icon-xs me-2 text-success"></i>{{ \App\Support\PlanFeatures::label($feature) }}
                          </li>
                        @endforeach
                      </ul>
                    @endif

                    @if($current && $company->subscription_status === 'active')
                      <button type="button" class="btn btn-label-primary w-100" disabled>Current plan</button>
                    @elseif(! $isOwner)
                      <button type="button" class="btn btn-label-secondary w-100" disabled
                              title="Only the company account can change the plan">
                        Company account only
                      </button>
                    @elseif($option->price_amount === null)
                      <button type="button" class="btn btn-label-secondary w-100" disabled>
                        Priced on request
                      </button>
                    @elseif(! $canPay)
                      <button type="button" class="btn btn-label-secondary w-100" disabled>
                        Payments unavailable
                      </button>
                    @else
                      <button type="button" class="btn btn-primary w-100 choose-plan"
                              data-plan-id="{{ $option->id }}"
                              data-plan-name="{{ $option->name }}"
                              data-plan-price="{{ number_format((float) $option->price_amount, 2) }}">
                        {{ $current ? 'Reactivate this plan' : 'Choose this plan' }}
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

{{-- Payment. Stripe's Payment Element renders the card fields inside this
     modal, so the customer pays without ever leaving the panel and no card
     detail touches our servers. --}}
@if($canPay)
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="payTitle">Subscribe</h5>
          <small class="text-muted" id="paySubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="payClose"></button>
      </div>

      <div class="modal-body">
        <div id="payLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Preparing payment…</span>
          </div>
          <p class="text-muted small mt-3 mb-0">Preparing secure payment…</p>
        </div>

        <form id="payForm" class="d-none">
          <div id="paymentElement"></div>

          <div class="alert alert-danger mt-3 d-none" id="payError" role="alert"></div>

          <div class="d-flex align-items-center gap-2 text-muted small mt-3">
            <i class="ti tabler-lock flex-shrink-0"></i>
            <span>Handled by Stripe. We never see or store your card number.</span>
          </div>
        </form>

        <div id="paySuccess" class="text-center py-5 d-none">
          <i class="ti tabler-circle-check text-success" style="font-size: 3rem;"></i>
          <h5 class="mt-3 mb-1">Payment received</h5>
          <p class="text-muted small mb-0">Your plan is active. Reloading the panel…</p>
        </div>
      </div>

      <div class="modal-footer" id="payFooter">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="paySubmit" disabled>
          <span class="spinner-border spinner-border-sm me-2 d-none" id="paySpinner" role="status"></span>
          <span id="paySubmitLabel">Pay</span>
        </button>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('page-script')
@if($canPay)
<script src="https://js.stripe.com/v3/"></script>
<script>
  // Vite ships the theme JS as deferred modules, so bootstrap and Stripe
  // are not defined until the document is ready.
  document.addEventListener('DOMContentLoaded', function () {
    const stripe = Stripe(@json($stripeKey));

    const modalEl = document.getElementById('payModal');
    const modal   = new bootstrap.Modal(modalEl);

    const loading  = document.getElementById('payLoading');
    const form     = document.getElementById('payForm');
    const success  = document.getElementById('paySuccess');
    const footer   = document.getElementById('payFooter');
    const errorBox = document.getElementById('payError');
    const submit   = document.getElementById('paySubmit');
    const spinner  = document.getElementById('paySpinner');
    const label    = document.getElementById('paySubmitLabel');

    let elements = null;

    function csrf() {
      return document.querySelector('meta[name="csrf-token"]')?.content
          || document.querySelector('input[name="_token"]')?.value;
    }

    function fail(message) {
      errorBox.textContent = message;
      errorBox.classList.remove('d-none');
      spinner.classList.add('d-none');
      submit.disabled = false;
    }

    function reset() {
      loading.classList.remove('d-none');
      form.classList.add('d-none');
      success.classList.add('d-none');
      footer.classList.remove('d-none');
      errorBox.classList.add('d-none');
      submit.disabled = true;
      spinner.classList.add('d-none');
      document.getElementById('paymentElement').innerHTML = '';
      elements = null;
    }

    document.querySelectorAll('.choose-plan').forEach(function (button) {
      button.addEventListener('click', async function () {
        reset();
        document.getElementById('payTitle').textContent = button.dataset.planName;
        document.getElementById('paySubtitle').textContent = '$' + button.dataset.planPrice + ' per month';
        label.textContent = 'Pay $' + button.dataset.planPrice;
        modal.show();

        try {
          const response = await fetch(@json(route('dispatcher.subscription.checkout')), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ subscription_plan_id: button.dataset.planId }),
          });

          const json = await response.json();

          if (!response.ok || !json.status) {
            loading.classList.add('d-none');
            form.classList.remove('d-none');
            fail(json.message || 'Could not start the payment.');
            return;
          }

          // A company already subscribed is simply moved to the new price;
          // Stripe prorates it, so there is no card to collect.
          if (json.data.requires_payment === false) {
            loading.classList.add('d-none');
            footer.classList.add('d-none');
            success.querySelector('h5').textContent = 'Plan changed';
            success.querySelector('p').textContent =
              'You are now on the ' + json.data.plan + '. The difference is prorated on your next invoice.';
            success.classList.remove('d-none');
            setTimeout(function () { window.location.reload(); }, 1800);
            return;
          }

          // Match the panel's own look rather than Stripe's default.
          const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

          elements = stripe.elements({
            clientSecret: json.data.client_secret,
            appearance: {
              theme: dark ? 'night' : 'stripe',
              variables: { colorPrimary: '#7367f0', borderRadius: '6px', fontFamily: 'inherit' },
            },
          });

          elements.create('payment', { layout: 'tabs' }).mount('#paymentElement');

          loading.classList.add('d-none');
          form.classList.remove('d-none');
          submit.disabled = false;
        } catch (error) {
          loading.classList.add('d-none');
          form.classList.remove('d-none');
          fail('Could not reach the payment service. Please try again.');
        }
      });
    });

    submit?.addEventListener('click', async function () {
      if (!elements) return;

      submit.disabled = true;
      spinner.classList.remove('d-none');
      errorBox.classList.add('d-none');

      // redirect: 'if_required' keeps the customer here unless their bank
      // insists on a 3-D Secure step.
      const { error } = await stripe.confirmPayment({
        elements,
        redirect: 'if_required',
        confirmParams: { return_url: window.location.href },
      });

      if (error) {
        fail(error.message || 'The payment was declined.');
        return;
      }

      // Stripe says it went through; ask our own server to read the
      // subscription back before believing it.
      try {
        await fetch(@json(route('dispatcher.subscription.confirm')), {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
      } catch (e) {
        // The webhook will still reconcile it.
      }

      form.classList.add('d-none');
      footer.classList.add('d-none');
      success.classList.remove('d-none');

      setTimeout(function () { window.location.reload(); }, 1600);
    });
  });
</script>
@endif
@endsection
