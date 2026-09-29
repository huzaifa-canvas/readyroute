@extends('layouts/layoutMaster')

@section('title', 'Platform Security')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="mb-4">
    <h4 class="fw-bold text-heading mb-1">Platform Security</h4>
    <p class="text-muted mb-0 small">Elevated access and account standing across the platform.</p>
  </div>

  {{-- Configuration warnings. Only shown when something is actually wrong, so
       a clean install renders nothing here. --}}
  @php
    $warnings = [];
    if ($health['app_debug'])        $warnings[] = 'Debug mode is on. Turn APP_DEBUG off in production — it exposes stack traces and configuration.';
    if (! $health['https'])          $warnings[] = 'APP_URL is not HTTPS. Tokens and signatures travel in clear text without it.';
    if ($health['socket_enabled'] && ! $health['socket_secret']) $warnings[] = 'The socket server is enabled but SOCKET_SECRET is empty, so internal endpoints will refuse every request.';
    if (! $health['push_enabled'])   $warnings[] = 'Push notifications are off. Drivers only receive in-app notifications until Firebase credentials are configured.';
  @endphp

  @if(count($warnings) > 0)
    <div class="alert alert-warning" role="alert">
      <div class="d-flex align-items-center mb-2">
        <i class="ti tabler-alert-triangle me-2"></i>
        <strong>{{ count($warnings) }} configuration {{ count($warnings) === 1 ? 'issue' : 'issues' }}</strong>
      </div>
      <ul class="mb-0 ps-4 small">
        @foreach($warnings as $warning)
          <li>{{ $warning }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Stat tiles --}}
  @php
    $tiles = [
      ['label' => 'Administrators', 'value' => $stats['admins'],        'icon' => 'tabler-shield-lock',  'colour' => 'primary'],
      ['label' => 'Companies',      'value' => $stats['companies'],     'icon' => 'tabler-building',     'colour' => 'info'],
      ['label' => 'Suspended',      'value' => $stats['suspended'],     'icon' => 'tabler-ban',          'colour' => 'warning'],
      ['label' => 'Archived',       'value' => $stats['archived'],      'icon' => 'tabler-archive',      'colour' => 'secondary'],
    ];
  @endphp

  <div class="row g-4 mb-4">
    @foreach($tiles as $tile)
    <div class="col-6 col-md-3">
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

  <div class="row g-4">

    {{-- Administrators --}}
    <div class="col-12 col-xl-5">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Administrators</h5>
          <small class="text-muted">Accounts with unrestricted platform access.</small>
        </div>
        <div class="card-body">
          @foreach($admins as $admin)
            <div class="d-flex align-items-center gap-3 {{ ! $loop->last ? 'mb-4' : '' }}">
              <div class="avatar avatar-sm flex-shrink-0">
                <img src="{{ $admin->avatar_url }}" alt="{{ $admin->name }}" class="rounded-circle" />
              </div>
              <div class="min-w-0 flex-grow-1">
                <span class="fw-semibold d-block text-truncate">{{ $admin->name }}</span>
                <small class="text-muted d-block text-truncate">{{ $admin->email }}</small>
              </div>
              <div class="flex-shrink-0 text-end">
                @if($admin->two_factor_confirmed_at)
                  <span class="badge bg-label-success" title="Two-factor authentication enabled">
                    <i class="ti tabler-lock-check icon-xs"></i>
                    <span class="d-none d-sm-inline ms-1">2FA</span>
                  </span>
                @else
                  <span class="badge bg-label-secondary" title="Two-factor authentication not enabled">
                    <i class="ti tabler-lock-open icon-xs"></i>
                    <span class="d-none d-sm-inline ms-1">No 2FA</span>
                  </span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- My account --}}
    <div class="col-12 col-xl-7">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Your Account</h5>
          <small class="text-muted">Signed in as {{ auth()->user()->email }}</small>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.security.password') }}" class="mb-4">
            @csrf
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password"
                       autocomplete="current-password"
                       class="form-control @error('current_password') is-invalid @enderror" required />
                @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="password">New Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror" required />
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password" class="form-control" required />
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">
                  <i class="ti tabler-key me-1"></i>Update Password
                </button>
              </div>
            </div>
          </form>

          <hr />

          <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
            <div>
              <h6 class="mb-1">Other sessions</h6>
              <p class="mb-0 text-muted small">
                Sign out everywhere except this browser.
              </p>
            </div>
            <form method="POST" action="{{ route('admin.security.sessions') }}" class="flex-shrink-0">
              @csrf
              <button type="submit" class="btn btn-label-secondary">
                <i class="ti tabler-logout me-1"></i>Sign out others
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Suspended accounts --}}
    @if($suspended->isNotEmpty())
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Suspended Accounts</h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Account</th>
                <th class="d-none d-sm-table-cell">Role</th>
                <th class="d-none d-md-table-cell">Suspended</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @foreach($suspended as $account)
              <tr>
                <td>
                  <span class="fw-semibold d-block text-truncate">{{ $account->name }}</span>
                  <small class="text-muted d-block text-truncate">{{ $account->email }}</small>
                  <small class="text-muted d-sm-none text-capitalize">{{ $account->role }}</small>
                </td>
                <td class="d-none d-sm-table-cell">
                  <span class="badge bg-label-secondary text-capitalize">{{ $account->role }}</span>
                </td>
                <td class="d-none d-md-table-cell">
                  <span class="text-body">{{ $account->suspended_at?->format('d M Y') ?? '—' }}</span>
                </td>
                <td>
                  <span class="text-muted small">{{ $account->suspension_reason ?: 'No reason recorded' }}</span>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
    @endif

  </div>
</div>
@endsection
