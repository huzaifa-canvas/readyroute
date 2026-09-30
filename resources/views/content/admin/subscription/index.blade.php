@extends('layouts/layoutMaster')

@section('title', 'Subscription Plans')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Subscription Plans</h4>
      <p class="text-muted mb-0 small">
        What each tier costs, how much it allows, and which screens it unlocks.
      </p>
    </div>
    <a href="{{ route('admin.subscription.create') }}" class="btn btn-primary">
      <i class="ti tabler-plus me-1"></i> Create Plan
    </a>
  </div>

  @unless($stripeReady)
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
      <i class="ti tabler-alert-triangle flex-shrink-0 mt-1"></i>
      <div>
        <strong>Stripe is not configured.</strong>
        Plans can still be assigned to companies by hand, but nobody can buy one
        and no revenue is recorded. Add <code>STRIPE_KEY</code> and <code>STRIPE_SECRET</code> to
        the environment.
      </div>
    </div>
  @endunless

  {{-- Plans --}}
  <div class="row g-4 mb-5">
    @forelse($plans as $plan)
    @php($optional = array_values(array_diff($plan->featureList(), \App\Support\PlanFeatures::mandatory())))
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card h-100 {{ $plan->is_featured ? 'border-primary' : '' }}">
        <div class="card-body d-flex flex-column">

          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <h5 class="fw-bold mb-0 text-break">{{ $plan->name }}</h5>
            <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
              @if($plan->is_featured)
                <span class="badge bg-primary">Popular</span>
              @endif
              @unless($plan->is_active ?? true)
                <span class="badge bg-label-secondary">Hidden</span>
              @endunless
            </div>
          </div>

          <div class="d-flex align-items-baseline gap-1 mb-3">
            <h3 class="fw-bold mb-0 text-primary">
              {{ $plan->price_amount !== null ? '$' . number_format((float) $plan->price_amount, 0) : $plan->price }}
            </h3>
            @if($plan->price_amount !== null)
              <span class="text-muted">{{ $plan->intervalSuffix() }}</span>
            @endif
          </div>

          <p class="text-muted small mb-3">{{ $plan->description }}</p>

          {{-- Limits --}}
          <div class="row g-2 text-center mb-3">
            @foreach([['vehicle', 'Vehicles'], ['driver', 'Drivers'], ['trip', 'Trips/mo']] as [$key, $label])
              <div class="col-4">
                <div class="border rounded py-2">
                  <span class="d-block fw-semibold small" style="font-variant-numeric: tabular-nums;">
                    {{ $plan->limitLabel($key) === 'Unlimited' ? '∞' : $plan->limitLabel($key) }}
                  </span>
                  <small class="text-muted" style="font-size: .6875rem;">{{ $label }}</small>
                </div>
              </div>
            @endforeach
          </div>

          {{-- What it unlocks beyond the always-on core --}}
          <div class="flex-grow-1 mb-3">
            @if(count($optional) > 0)
              <div class="d-flex flex-wrap gap-1">
                @foreach(array_slice($optional, 0, 5) as $feature)
                  <span class="badge bg-label-success">{{ \App\Support\PlanFeatures::label($feature) }}</span>
                @endforeach
                @if(count($optional) > 5)
                  <span class="badge bg-label-secondary">+{{ count($optional) - 5 }} more</span>
                @endif
              </div>
            @else
              <small class="text-muted">Core dispatching only.</small>
            @endif
          </div>

          {{-- Standing --}}
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pt-3 border-top">
            <small class="text-muted">
              <i class="ti tabler-building icon-xs me-1"></i>
              {{ $plan->companies_count }} {{ $plan->companies_count === 1 ? 'company' : 'companies' }}
            </small>
            @if($plan->stripe_price_id)
              <small class="text-success">
                <i class="ti tabler-circle-check icon-xs me-1"></i>Live in Stripe
              </small>
            @elseif($plan->price_amount === null)
              <small class="text-muted">
                <i class="ti tabler-message icon-xs me-1"></i>Contact us
              </small>
            @else
              <small class="text-muted">
                <i class="ti tabler-cloud-upload icon-xs me-1"></i>Syncs on save
              </small>
            @endif
          </div>

          <div class="d-flex gap-2">
            <a href="{{ route('admin.subscription.edit', $plan->id) }}" class="btn btn-outline-primary flex-grow-1 fw-bold">
              <i class="ti tabler-edit me-1"></i>Edit Plan
            </a>
            <form action="{{ route('admin.subscription.delete', $plan->id) }}" method="POST">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-icon btn-label-danger"
                      title="Delete plan" aria-label="Delete {{ $plan->name }}"
                      onclick="return confirm('Delete the {{ $plan->name }}?')">
                <i class="ti tabler-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    @empty
    <div class="col-12">
      <div class="card">
        <div class="card-body text-center py-5 text-muted">
          <i class="ti tabler-file-dollar fs-1 d-block mb-2 text-secondary"></i>
          <h6>No plans yet</h6>
          <p class="mb-3 small">Create the first tier so companies have something to subscribe to.</p>
          <a href="{{ route('admin.subscription.create') }}" class="btn btn-primary btn-sm">Create Plan</a>
        </div>
      </div>
    </div>
    @endforelse
  </div>

  {{-- Invoices --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h5 class="fw-bold text-heading mb-1">Subscription Payments</h5>
      <p class="text-muted mb-0 small">
        What our companies have paid. Recorded here as Stripe reports each payment.
      </p>
    </div>
    @if($invoices->isNotEmpty())
      <span class="badge bg-label-success px-3 py-2" style="font-variant-numeric: tabular-nums;">
        ${{ number_format($invoices->where('status', 'paid')->sum('amount'), 2) }} collected
      </span>
    @endif
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Invoice</th>
            <th class="d-none d-md-table-cell">Company</th>
            <th class="d-none d-lg-table-cell">Plan</th>
            <th class="d-none d-sm-table-cell">Date</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($invoices as $invoice)
          <tr>
            <td>
              <span class="fw-semibold d-block">{{ $invoice->number }}</span>
              <small class="text-muted d-md-none d-block text-truncate">
                {{ $invoice->company?->name ?? 'Removed company' }}
              </small>
            </td>
            <td class="d-none d-md-table-cell">
              @if($invoice->company)
                <a href="{{ route('admin.company.show', $invoice->company->id) }}"
                   class="text-body d-block text-truncate" style="max-width: 200px;">
                  {{ $invoice->company->name }}
                </a>
              @else
                <span class="text-muted">Removed company</span>
              @endif
            </td>
            <td class="d-none d-lg-table-cell">
              <span class="badge bg-label-secondary">{{ $invoice->plan?->name ?? '—' }}</span>
            </td>
            <td class="d-none d-sm-table-cell">
              <span class="text-body">{{ $invoice->issued_at?->format('d M Y') ?? '—' }}</span>
              <small class="text-muted d-block">{{ $invoice->issued_at?->diffForHumans() }}</small>
            </td>
            <td class="text-end fw-semibold" style="font-variant-numeric: tabular-nums;">
              {{ number_format((float) $invoice->amount, 2) }}
              <small class="text-muted">{{ strtoupper($invoice->currency) }}</small>
            </td>
            <td>
              <span class="badge {{ $invoice->statusClass() }}">{{ $invoice->statusLabel() }}</span>
            </td>
            <td class="text-end">
              @if($invoice->hosted_url)
                <a href="{{ $invoice->hosted_url }}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-label-primary" aria-label="Open {{ $invoice->number }}">
                  <i class="ti tabler-external-link"></i>
                </a>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="ti tabler-receipt-off fs-1 d-block mb-2 text-secondary"></i>
              <h6>No payments yet</h6>
              {{-- Say why it is empty rather than leaving the admin guessing. --}}
              <p class="mb-0 small">
                @if(! $stripeReady)
                  Stripe is not configured, so no payment has been taken.
                @elseif($assignedOnly > 0)
                  {{ $assignedOnly }} {{ $assignedOnly === 1 ? 'company was' : 'companies were' }}
                  given a plan directly rather than buying one, so no card was charged.
                  A payment appears here the first time a company subscribes.
                @else
                  A payment appears here the first time a company subscribes.
                @endif
              </p>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
