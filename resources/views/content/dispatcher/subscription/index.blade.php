@extends('layouts/layoutMaster')

@section('title', 'My Subscription')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php
    $plan    = $company->subscriptionPlan;
    $isOwner = auth()->user()->isCompanyOwner() || auth()->user()->isAdmin();
    $labels  = ['vehicle' => 'Vehicles', 'driver' => 'Drivers', 'trip' => 'Trips this month'];

    // Someone paying today is changing plans, not buying one, and the two
    // read very differently: they already have a date they are paid up to.
    $isPaying    = $company->subscription_status === 'active';
    $pendingPlan = $company->pendingPlan;
    $changesOn   = $company->pending_plan_starts_at;
    $paidUntil   = $company->cancels_at?->isFuture() ? $company->cancels_at : $company->renews_at;
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
        @php($hasEnded = $company->subscription_status !== 'active')

        <div class="card-header">
          {{-- The card is a different thing once the subscription is over: it
               is a record of what they used to be on, not what they are on.
               Calling it "Current plan" there is what made it read as still
               running. --}}
          <h5 class="mb-0">{{ $hasEnded ? 'Previous plan' : 'Current plan' }}</h5>
        </div>
        <div class="card-body">
          @if($plan && $hasEnded)
            {{-- Ended. Shown greyed and past-tense, with no price and no
                 billing date — there is nothing more to pay, and leaving a
                 future date on the page was the confusing part. --}}
            <div class="text-center py-3">
              <span class="badge bg-label-danger mb-3">
                <i class="ti tabler-circle-x me-1"></i>Subscription ended
              </span>

              <h4 class="fw-bold mb-1 text-muted text-decoration-line-through">{{ $plan->name }}</h4>
              <p class="text-muted small mb-3">{{ $plan->description }}</p>

              <dl class="row mb-0 text-start">
                <dt class="col-6 text-muted fw-normal small py-1">Was on</dt>
                <dd class="col-6 py-1 text-muted">{{ $plan->name }}</dd>

                <dt class="col-6 text-muted fw-normal small py-1">Started</dt>
                <dd class="col-6 py-1 text-muted">{{ $company->subscribed_at?->format('d M Y') ?? '—' }}</dd>

                <dt class="col-6 text-muted fw-normal small py-1">Ended</dt>
                <dd class="col-6 py-1 text-muted">
                  {{ ($company->cancels_at ?: $company->renews_at)?->format('d M Y') ?? '—' }}
                </dd>
              </dl>

              <a href="#available-plans" class="btn btn-primary w-100 mt-4">
                <i class="ti tabler-rocket me-1"></i>Choose a plan to start again
              </a>
            </div>
          @elseif($plan)
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

              {{-- Only while something is actually going to be charged. --}}
              @if($company->cancels_at?->isFuture())
                <dt class="col-6 text-muted fw-normal small py-1">Access until</dt>
                <dd class="col-6 py-1">
                  {{ $company->cancels_at->format('d M Y') }}
                  <small class="text-muted d-block">{{ $company->cancels_at->diffForHumans() }}</small>
                </dd>
              @else
                <dt class="col-6 text-muted fw-normal small py-1">Next billing date</dt>
                <dd class="col-6 py-1">
                  {{ $company->renews_at?->format('d M Y') ?? '—' }}
                  @if($company->renews_at)
                    <small class="text-muted d-block">{{ $company->renews_at->diffForHumans() }}</small>
                  @endif
                </dd>
              @endif

              @if($pendingPlan)
                <dt class="col-6 text-muted fw-normal small py-1">Then moves to</dt>
                <dd class="col-6 py-1">
                  {{ $pendingPlan->name }}
                  <small class="text-muted d-block">{{ $pendingPlan->priceLabel() }}</small>
                </dd>
              @endif
            </dl>

            @if($pendingPlan)
              {{-- The whole point of a scheduled change is that nothing
                   happens today, so the card has to say so plainly: which
                   plan is running, until when, and what replaces it. Without
                   this the page looks identical before and after the change
                   was asked for. --}}
              <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0 py-2 px-3" role="status">
                <i class="ti tabler-arrow-right-circle flex-shrink-0 mt-1"></i>
                <div class="small">
                  You stay on <strong>{{ $plan->name }}</strong> until
                  <strong>{{ $changesOn?->format('d M Y') ?? 'the end of this period' }}</strong>.
                  <strong>{{ $pendingPlan->name }}</strong> starts that day at
                  {{ $pendingPlan->priceLabel() }} — nothing to pay before then.
                </div>
              </div>
            @endif
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

          @php($endingOn = $company->cancels_at?->isFuture() ? $company->cancels_at : null)
          @php($canManage = auth()->user()->isCompanyOwner() || auth()->user()->isAdmin())

          @if($endingOn)
            {{-- Already cancelled, but paid up to the end of the period. The
                 Cancel button must not come back here: pressing it again sends
                 Stripe a second cancellation it will refuse. --}}
            <div class="alert alert-warning mb-0 py-2 px-3">
              <i class="ti tabler-calendar-x me-1"></i>
              Cancelled. You keep full access until
              <strong>{{ $endingOn->format('d M Y') }}</strong>
              ({{ $endingOn->diffForHumans() }}), then the panel becomes read-only.
            </div>

            @if($canManage)
              <form method="POST" action="{{ route('dispatcher.subscription.resume') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-label-primary">
                  <i class="ti tabler-rotate-clockwise me-1"></i>Keep my subscription
                </button>
              </form>
            @endif

          @elseif($pendingPlan && $canManage)
            <form method="POST" action="{{ route('dispatcher.subscription.plan-change.cancel') }}">
              @csrf
              <button type="submit" class="btn btn-sm btn-label-primary"
                      onclick="return confirm('Call off the move to {{ $pendingPlan->name }} and stay on {{ $plan->name }}?')">
                <i class="ti tabler-rotate-clockwise me-1"></i>Stay on {{ $plan->name }}
              </button>
            </form>

            <form method="POST" action="{{ route('dispatcher.subscription.cancel') }}">
              @csrf
              <button type="submit" class="btn btn-text-danger btn-sm p-0"
                      onclick="return confirm('Cancel the subscription? You keep access until the end of the period you have paid for.')">
                Cancel subscription
              </button>
            </form>

          @elseif($company->subscription_status === 'active' && $canManage)
            <form method="POST" action="{{ route('dispatcher.subscription.cancel') }}">
              @csrf
              <button type="submit" class="btn btn-text-danger btn-sm p-0"
                      onclick="return confirm('Cancel the subscription? You keep access until the end of the period you have paid for.')">
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
          <h5 class="mb-0" id="available-plans">Available plans</h5>
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
                      {{-- "Current" only while it actually is. After the
                           subscription ends it is the plan they used to be on,
                           and leaving the badge as-is read like it was still
                           running. --}}
                      @if($current && $company->subscription_status === 'active')
                        <span class="badge bg-primary flex-shrink-0">Current</span>
                      @elseif($pendingPlan && $pendingPlan->id === $option->id)
                        <span class="badge bg-label-info flex-shrink-0">Starts {{ $changesOn?->format('d M') }}</span>
                      @elseif($current)
                        <span class="badge bg-label-secondary flex-shrink-0">Previous</span>
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
                    @elseif($pendingPlan && $pendingPlan->id === $option->id)
                      <button type="button" class="btn btn-label-info w-100" disabled>
                        Starts {{ $changesOn?->format('d M Y') }}
                      </button>
                    @elseif($isPaying && ! $current)
                      {{-- Already paying, so this is a change of plan rather
                           than a purchase: it opens the dialog that explains
                           when it takes effect instead of going straight to a
                           card form. --}}
                      <button type="button" class="btn btn-primary w-100 change-plan"
                              data-plan-id="{{ $option->id }}"
                              data-plan-name="{{ $option->name }}"
                              data-plan-price="{{ number_format((float) $option->price_amount, 2) }}"
                              data-plan-price-label="{{ $option->priceLabel() }}"
                              data-direction="{{ $plan && $option->price_amount > $plan->price_amount ? 'up' : 'down' }}">
                        @if($plan && $option->price_amount > $plan->price_amount)
                          Upgrade to {{ $option->name }}
                        @else
                          Switch to {{ $option->name }}
                        @endif
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

{{-- Changing plan. Only reachable with a subscription already running, which
     is what makes the timing a real choice: there is a period they have paid
     for, and they can either let it finish or cut it short and settle the
     difference. The dialog exists because doing either one silently is what
     made the old behaviour impossible to predict. --}}
@if($canPay && $isPaying)
<div class="modal fade" id="changeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Move to <span id="changePlanName"></span></h5>
          <small class="text-muted">
            You are on {{ $plan?->name ?? 'your current plan' }}@if($paidUntil), paid until {{ $paidUntil->format('d M Y') }}@endif.
          </small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-danger d-none" id="changeError" role="alert"></div>

        @if($company->cancels_at?->isFuture())
          {{-- Choosing a plan is Stripe's own signal to keep the subscription
               running, so the cancellation goes away either way. Better said
               here than discovered later on the billing page. --}}
          <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3" role="alert">
            <i class="ti tabler-info-circle flex-shrink-0 mt-1"></i>
            <div class="small">
              Your subscription is set to end on
              <strong>{{ $company->cancels_at->format('d M Y') }}</strong>.
              Moving to another plan calls that off and the subscription carries on.
            </div>
          </div>
        @endif

        <div class="row g-3" id="changeChoices">
          <div class="col-12 col-md-6">
            <div class="border rounded h-100 p-4 d-flex flex-column">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="ti tabler-calendar-check text-primary"></i>
                <h6 class="mb-0">At your renewal date</h6>
              </div>
              <p class="text-muted small flex-grow-1 mb-3">
                You keep {{ $plan?->name ?? 'your current plan' }} and everything on it
                @if($paidUntil)until <strong>{{ $paidUntil->format('d M Y') }}</strong>.@else until this period ends.@endif
                <span id="changeRenewalLine"></span> starts that day and is billed then.
                <strong>Nothing is charged today.</strong>
              </p>
              <button type="button" class="btn btn-primary w-100" id="changeAtRenewal">
                <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                @if($paidUntil)Start on {{ $paidUntil->format('d M Y') }}@else Start at renewal @endif
              </button>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="border rounded h-100 p-4 d-flex flex-column">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="ti tabler-bolt text-warning"></i>
                <h6 class="mb-0">Right now</h6>
              </div>
              <p class="text-muted small flex-grow-1 mb-3">
                You move across immediately. Stripe credits the unused part of
                {{ $plan?->name ?? 'your current plan' }} and charges the
                difference today, so an upgrade costs the gap rather than the
                full price. After that you pay <span id="changeNowPrice"></span>.
              </p>
              <button type="button" class="btn btn-label-primary w-100" id="changeNow">
                <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                Switch now and pay the difference
              </button>
            </div>
          </div>
        </div>

        <div id="changeDone" class="text-center py-5 d-none">
          <i class="ti tabler-circle-check text-success" style="font-size: 3rem;"></i>
          <h5 class="mt-3 mb-1" id="changeDoneTitle"></h5>
          <p class="text-muted small mb-0" id="changeDoneBody"></p>
        </div>
      </div>

      <div class="modal-footer" id="changeFooter">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Keep my current plan</button>
      </div>
    </div>
  </div>
</div>
@endif

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

          // A company already subscribed is moved to the new price against the
          // card Stripe already holds, so there is nothing to collect here.
          if (json.data.requires_payment === false) {
            loading.classList.add('d-none');
            footer.classList.add('d-none');
            success.querySelector('h5').textContent = 'Plan changed';
            success.querySelector('p').textContent = json.data.message
              || ('You are now on the ' + json.data.plan + '. The difference for the rest of this period has been charged.');
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

    /*
     * Changing plan while one is already running.
     *
     * Both choices go to the same endpoint; `when` is the whole difference.
     * Neither needs a card: Stripe already has one on file for this
     * subscription, so the immediate switch is invoiced against it rather
     * than collected through a payment form.
     */
    const changeEl    = document.getElementById('changeModal');
    const changeModal = changeEl ? new bootstrap.Modal(changeEl) : null;

    if (changeModal) {
      const choices   = document.getElementById('changeChoices');
      const done      = document.getElementById('changeDone');
      const doneTitle = document.getElementById('changeDoneTitle');
      const doneBody  = document.getElementById('changeDoneBody');
      const changeFoot  = document.getElementById('changeFooter');
      const changeError = document.getElementById('changeError');
      const atRenewal = document.getElementById('changeAtRenewal');
      const rightNow  = document.getElementById('changeNow');

      let chosen = null;

      function busy(button, on) {
        button.querySelector('.spinner-border').classList.toggle('d-none', !on);
        atRenewal.disabled = on;
        rightNow.disabled  = on;
      }

      document.querySelectorAll('.change-plan').forEach(function (button) {
        button.addEventListener('click', function () {
          chosen = button.dataset;
          document.getElementById('changePlanName').textContent = chosen.planName;
          document.getElementById('changeRenewalLine').textContent = chosen.planName;
          document.getElementById('changeNowPrice').textContent = chosen.planPriceLabel;

          choices.classList.remove('d-none');
          done.classList.add('d-none');
          changeFoot.classList.remove('d-none');
          changeError.classList.add('d-none');
          atRenewal.disabled = false;
          rightNow.disabled  = false;
          changeModal.show();
        });
      });

      async function change(when, button) {
        if (!chosen) return;
        busy(button, true);
        changeError.classList.add('d-none');

        try {
          const response = await fetch(@json(route('dispatcher.subscription.checkout')), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ subscription_plan_id: chosen.planId, when: when }),
          });

          const json = await response.json();

          if (!response.ok || !json.status) {
            changeError.textContent = json.message || 'That change could not be made.';
            changeError.classList.remove('d-none');
            busy(button, false);
            return;
          }

          choices.classList.add('d-none');
          changeFoot.classList.add('d-none');
          doneTitle.textContent = json.data.scheduled ? 'Change scheduled' : 'Plan changed';
          doneBody.textContent  = json.data.message
            || ('You are now on the ' + json.data.plan + '.');
          done.classList.remove('d-none');

          setTimeout(function () { window.location.reload(); }, 2600);
        } catch (error) {
          changeError.textContent = 'Could not reach the server. Please try again.';
          changeError.classList.remove('d-none');
          busy(button, false);
        }
      }

      atRenewal.addEventListener('click', function () { change('renewal', atRenewal); });
      rightNow.addEventListener('click',  function () { change('now', rightNow); });
    }

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
