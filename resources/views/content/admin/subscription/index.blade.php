@extends('layouts/layoutMaster')

@section('title', 'SaaS Subscription & Plan')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Success/Error Alerts --}}
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

  {{-- Page Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
      <h4 class="fw-bold text-heading mb-1">SaaS Subscription & Plan</h4>
      <p class="text-muted mb-0">Manage subscription tiers and pricing plans for client organizations</p>
    </div>
    <div>
      <a href="{{ route('admin.subscription.create') }}" class="btn btn-primary">
        <i class="ti tabler-plus me-1"></i> Create Plan
      </a>
    </div>
  </div>

  {{-- Subscription Plans Grid --}}
  <div class="row g-4 mb-5">
    @forelse($plans as $plan)
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 border">
        <div class="card-body d-flex flex-column justify-content-between p-4">
          <div>
            {{-- Plan Title --}}
            <h5 class="fw-bold text-heading mb-2">{{ $plan->name }}</h5>
            
            {{-- Plan Price --}}
            <div class="d-flex align-items-baseline mb-3">
              <h3 class="fw-extrabold mb-0 text-warning" style="color: #ffab00 !important; font-size: 2rem;">
                {{ $plan->price }}
              </h3>
              @if($plan->billing_period)
                <span class="text-muted ms-1 fs-6">{{ $plan->billing_period }}</span>
              @endif
            </div>

            {{-- Plan Description --}}
            <p class="text-muted mb-4" style="font-size: 0.88rem; min-height: 42px;">
              {{ $plan->description }}
            </p>
          </div>

          {{-- Action Buttons --}}
          <div class="pt-2">
            <div class="d-flex gap-2">
              <a href="{{ route('admin.subscription.edit', $plan->id) }}" class="btn btn-label-secondary flex-grow-1 fw-bold">
                <i class="ti tabler-edit me-1"></i> Edit Plan
              </a>
              <form action="{{ route('admin.subscription.delete', $plan->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-icon btn-label-danger" onclick="return confirm('Are you sure you want to delete this plan?')" title="Delete Plan">
                  <i class="ti tabler-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    @empty
    <div class="col-12 text-center py-5">
      <div class="text-muted">
        <i class="ti tabler-file-dollar fs-1 d-block mb-2"></i>
        No subscription plans found. Click "+ Create Plan" to add one.
      </div>
    </div>
    @endforelse
  </div>

  {{-- Recent Invoices Section --}}
  <div class="mb-4">
    <h4 class="fw-bold text-heading mb-3">Recent Invoices</h4>

    @forelse($invoices as $invoice)
    <div class="card mb-3 border">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
        <div>
          <h6 class="fw-bold text-heading mb-1">{{ $invoice->number }} · {{ $invoice->company }}</h6>
          <small class="text-muted">{{ $invoice->date }} - {{ $invoice->plan }}</small>
        </div>
        <div class="text-end">
          <h5 class="fw-bold text-heading mb-1">{{ $invoice->amount }}</h5>
          @if($invoice->status == 'Paid')
            <span class="badge bg-label-success text-success px-3 py-1 fw-bold">Paid</span>
          @else
            <span class="badge bg-label-danger text-danger px-3 py-1 fw-bold">Failed</span>
          @endif
        </div>
      </div>
    </div>
    @empty
    <div class="card border">
      <div class="card-body text-center py-4 text-muted">
        <i class="ti tabler-receipt-off fs-2 d-block mb-1"></i>
        No recent invoices found.
      </div>
    </div>
    @endforelse
  </div>

</div>
@endsection
