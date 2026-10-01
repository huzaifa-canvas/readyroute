@extends('layouts/layoutMaster')

@section('title', 'Manage — ' . $company->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('admin.company.list') }}">Companies</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $company->name }}</li>
    </ol>
  </nav>

  {{-- Archived banner --}}
  @if($company->trashed())
    <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
      <span>
        <i class="ti tabler-archive me-2"></i>
        This company is archived — it was removed on {{ $company->deleted_at->format('d M Y') }}.
      </span>
      <form action="{{ route('admin.company.restore', $company->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-sm btn-label-primary">
          <i class="ti tabler-rotate me-1"></i>Restore
        </button>
      </form>
    </div>
  @elseif($company->isSuspended())
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
      <span>
        <i class="ti tabler-ban me-2"></i>
        Suspended {{ $company->suspended_at?->diffForHumans() }}.
        @if($company->suspension_reason)
          <span class="fw-semibold">Reason:</span> {{ $company->suspension_reason }}
        @endif
      </span>
      <form action="{{ route('admin.company.activate', $company->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-sm btn-success">
          <i class="ti tabler-player-play me-1"></i>Reactivate
        </button>
      </form>
    </div>
  @endif

  {{-- Header card --}}
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
        <div class="avatar avatar-xl flex-shrink-0 mx-auto mx-md-0">
          <img src="{{ $company->avatar_url }}" alt="{{ $company->name }}" class="rounded-circle" />
        </div>

        <div class="flex-grow-1 text-center text-md-start min-w-0">
          <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
            <h4 class="fw-bold text-heading mb-0 text-break">{{ $company->name }}</h4>
            <span class="badge {{ $company->statusClass() }}">{{ $company->statusLabel() }}</span>
          </div>
          <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3 text-muted small">
            <span class="text-break"><i class="ti tabler-mail icon-xs me-1"></i>{{ $company->email }}</span>
            @if($company->phone_number)
              <span><i class="ti tabler-phone icon-xs me-1"></i>{{ $company->phone_number }}</span>
            @endif
            @if($company->getMeta('region'))
              <span><i class="ti tabler-map-pin icon-xs me-1"></i>{{ $company->getMeta('region') }}</span>
            @endif
            <span><i class="ti tabler-calendar icon-xs me-1"></i>Joined {{ $company->created_at?->format('d M Y') }}</span>
          </div>
        </div>

        @unless($company->trashed())
        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-end">
          <a href="{{ route('admin.company.edit', $company->id) }}" class="btn btn-primary">
            <i class="ti tabler-edit me-1"></i>Edit
          </a>
          @if($company->isSuspended())
            <form action="{{ route('admin.company.activate', $company->id) }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-label-success"><i class="ti tabler-player-play me-1"></i>Reactivate</button>
            </form>
          @else
            <button type="button" class="btn btn-label-warning" data-bs-toggle="modal" data-bs-target="#suspendModal">
              <i class="ti tabler-ban me-1"></i>Suspend
            </button>
          @endif
        </div>
        @endunless
      </div>
    </div>
  </div>

  {{-- Stat tiles --}}
  @php
    $tiles = [
      ['label' => 'Drivers',        'value' => $stats['drivers'],          'icon' => 'tabler-steering-wheel', 'colour' => 'primary'],
      ['label' => 'Online now',     'value' => $stats['drivers_online'],   'icon' => 'tabler-broadcast',      'colour' => 'success'],
      ['label' => 'Vehicles',       'value' => $stats['vehicles'],         'icon' => 'tabler-car',            'colour' => 'info'],
      ['label' => 'Clients',        'value' => $stats['clients'],          'icon' => 'tabler-user-square',    'colour' => 'warning'],
      ['label' => 'Trips total',    'value' => $stats['trips'],            'icon' => 'tabler-route',          'colour' => 'secondary'],
      ['label' => 'Trips this month','value' => $stats['trips_this_month'],'icon' => 'tabler-calendar-stats', 'colour' => 'primary'],
    ];
  @endphp

  <div class="row g-4 mb-4">
    @foreach($tiles as $tile)
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body text-center p-3">
          <div class="avatar avatar-sm mx-auto mb-2">
            <span class="avatar-initial rounded bg-label-{{ $tile['colour'] }}">
              <i class="ti {{ $tile['icon'] }}"></i>
            </span>
          </div>
          <h4 class="fw-bold mb-0">{{ $tile['value'] }}</h4>
          <small class="text-muted">{{ $tile['label'] }}</small>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Subscription and free access.
       Free access is a plan the company holds without paying. The gate reads
       the date, so it lapses on its own; the admin pushes it out from here. --}}
  @php
    $freeUntil   = $company->freeAccessEndsAt();
    $freeExpired = $company->freeAccessExpiredAt();
    $isPaying    = $company->subscription_status === 'active';
  @endphp

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h5 class="mb-0">Subscription</h5>
      <span class="badge {{ $company->subscriptionStatusClass() }}">{{ $company->subscriptionStatusLabel() }}</span>
    </div>

    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-5">
          <div class="mb-3">
            <small class="text-muted d-block">Current plan</small>
            <span class="fw-semibold text-heading">
              {{ $company->subscriptionPlan?->name ?? 'None' }}
            </span>
            @if($company->subscriptionPlan)
              <small class="text-muted">
                ({{ $isPaying ? 'paid' : ($freeUntil ? 'free access' : 'not active') }})
              </small>
            @endif
          </div>

          @if($freeUntil)
            <div class="alert alert-info mb-0 py-2 px-3">
              <i class="ti tabler-gift me-1"></i>
              Free until <strong>{{ $freeUntil->format('d M Y') }}</strong>
              — {{ $freeUntil->diffForHumans() }}.
            </div>
          @elseif($freeExpired)
            <div class="alert alert-danger mb-0 py-2 px-3">
              <i class="ti tabler-clock-off me-1"></i>
              Free access ended <strong>{{ $freeExpired->format('d M Y') }}</strong>.
              They can sign in and read, but cannot create anything until they
              subscribe or you extend the date.
            </div>
          @elseif($isPaying)
            <div class="alert alert-success mb-0 py-2 px-3">
              <i class="ti tabler-credit-card me-1"></i>
              Paying by card. Granting free access would let them keep using the
              panel if that subscription lapses.
            </div>
          @else
            <div class="alert alert-secondary mb-0 py-2 px-3">
              <i class="ti tabler-info-circle me-1"></i>
              No subscription. They can sign in and look around, but cannot
              create trips, drivers, vehicles or clients.
            </div>
          @endif
        </div>

        <div class="col-md-7">
          <form action="{{ route('admin.company.free-access', $company->id) }}" method="POST">
            @csrf
            <div class="row g-3 align-items-end">
              <div class="col-sm-6">
                <label class="form-label fw-semibold" for="fa_plan">Plan</label>
                <select id="fa_plan" name="subscription_plan_id" class="form-select" required>
                  @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" {{ $company->subscription_plan_id == $plan->id ? 'selected' : '' }}>
                      {{ $plan->name }} ({{ $plan->price }}{{ $plan->billing_period ? ' / ' . $plan->billing_period : '' }})
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-sm-6">
                <label class="form-label fw-semibold" for="fa_until">Free until</label>
                <input type="date" id="fa_until" name="free_until" class="form-control" required
                       min="{{ now()->addDay()->toDateString() }}"
                       value="{{ old('free_until', $freeUntil?->toDateString()) }}" />
              </div>

              <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="ti tabler-gift me-1"></i>
                  {{ $freeUntil ? 'Update free access' : 'Grant free access' }}
                </button>

                {{-- Quick extensions fill the date field rather than
                     submitting a second value for it, so the admin sees the
                     date they are about to set before committing to it. They
                     count from whichever is later, today or the current end
                     date, so extending can never shorten the period. --}}
                @php($extendFrom = $freeUntil ?: now())
                @foreach([30 => '+30 days', 90 => '+90 days', 365 => '+1 year'] as $days => $label)
                  <button type="button" class="btn btn-label-primary"
                          data-set-date="{{ $extendFrom->copy()->addDays($days)->toDateString() }}">
                    {{ $label }}
                  </button>
                @endforeach

                @if($freeUntil || $freeExpired)
                  {{-- formnovalidate: withdrawing needs neither field, and the
                       date is empty once a period has already lapsed. --}}
                  <button type="submit" name="action" value="revoke" formnovalidate
                          class="btn btn-label-danger ms-auto"
                          onclick="return confirm('Withdraw free access from {{ $company->name }}? They will not be able to create anything until they subscribe.');">
                    Withdraw
                  </button>
                @endif
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    {{-- Recent trips --}}
    <div class="col-12 col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0">Recent Trips</h5>
          <span class="badge bg-label-secondary">{{ $recentTrips->count() }} shown</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Trip</th>
                <th class="d-none d-sm-table-cell">Driver</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @forelse($recentTrips as $trip)
              @php($tripStatus = $trip->statusEnum())
              <tr>
                <td>
                  <span class="fw-semibold d-block">{{ $trip->reference() }}</span>
                  <small class="text-muted d-block text-truncate" style="max-width: 220px;">
                    {{ $trip->passengerName() }}
                  </small>
                  <small class="text-muted d-sm-none">
                    {{ $trip->driver?->name ?? 'Unassigned' }}
                  </small>
                </td>
                <td class="d-none d-sm-table-cell">
                  <span class="text-body">{{ $trip->driver?->name ?? '—' }}</span>
                </td>
                <td>
                  <span class="badge {{ $tripStatus?->badgeClass() ?? 'bg-label-secondary' }}">
                    {{ $tripStatus?->label() ?? 'Unknown' }}
                  </span>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center py-4 text-muted">No trips yet.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Panel staff --}}
    <div class="col-12 col-lg-5">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="mb-0">Panel Users</h5>
          <span class="badge bg-label-secondary">{{ $stats['staff'] }}</span>
        </div>
        <div class="card-body">
          @forelse($staff as $member)
            <div class="d-flex align-items-center gap-3 {{ ! $loop->last ? 'mb-4' : '' }}">
              <div class="avatar avatar-sm flex-shrink-0">
                <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}" class="rounded-circle" />
              </div>
              <div class="min-w-0 flex-grow-1">
                <span class="fw-semibold d-block text-truncate">{{ $member->name }}</span>
                <small class="text-muted d-block text-truncate">{{ $member->email }}</small>
              </div>
              <span class="badge bg-label-primary flex-shrink-0">
                {{ $member->accessRole?->name ?? 'No role' }}
              </span>
            </div>
          @empty
            <div class="text-center py-4 text-muted">
              <i class="ti tabler-users fs-2 d-block mb-2 text-secondary"></i>
              <p class="mb-0 small">
                This company has not added any panel users yet.<br>
                They manage their own team from the dispatcher panel.
              </p>
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- Danger zone --}}
  @unless($company->trashed())
  <div class="card border-danger mt-4">
    <div class="card-body d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
      <div>
        <h6 class="mb-1 text-danger">Archive this company</h6>
        <p class="mb-0 text-muted small">
          The account is signed out and hidden from the list. Trips, drivers and clients are kept,
          and the company can be restored at any time.
        </p>
      </div>
      <form action="{{ route('admin.company.delete', $company->id) }}" method="POST" class="flex-shrink-0">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-label-danger"
                onclick="return confirm('Archive {{ $company->name }}?')">
          <i class="ti tabler-archive me-1"></i>Archive
        </button>
      </form>
    </div>
  </div>
  @endunless
</div>

{{-- Suspend modal --}}
@unless($company->trashed() || $company->isSuspended())
<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" action="{{ route('admin.company.suspend', $company->id) }}" method="POST">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Suspend {{ $company->name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">
          The company keeps all its data but is signed out immediately and cannot log in until reactivated.
        </p>
        <label class="form-label" for="suspension_reason">Reason (optional)</label>
        <input type="text" id="suspension_reason" name="suspension_reason" class="form-control"
               maxlength="255" placeholder="e.g. Payment overdue" />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning">Suspend</button>
      </div>
    </form>
  </div>
</div>
@endunless
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // The quick-extend buttons only fill the date field; submitting stays a
    // deliberate second action, so a mis-click cannot change a company's
    // billing standing.
    const field = document.getElementById('fa_until');
    if (!field) return;

    document.querySelectorAll('[data-set-date]').forEach(function (button) {
      button.addEventListener('click', function () {
        field.value = button.dataset.setDate;
        field.focus();
      });
    });
  });
</script>
@endsection
