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
          @if(session('success_profile'))
            <div class="alert alert-success alert-dismissible" role="alert">
              <i class="ti tabler-circle-check me-1"></i>{{ session('success_profile') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          @endif

          {{-- The admin's own details. They live beside the password because
               this is the only account screen the platform admin has, and two
               pages for one account would be two places to go looking. --}}
          <form method="POST" action="{{ route('admin.security.profile') }}"
                enctype="multipart/form-data" class="mb-4 pb-4 border-bottom">
            @csrf

            <h6 class="mb-3">Your details</h6>

            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="avatar avatar-lg">
                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}"
                     class="rounded-circle" id="adminAvatarPreview" />
              </div>
              <div>
                <label class="btn btn-sm btn-label-primary mb-1" for="avatar">
                  <i class="ti tabler-upload me-1"></i>Change photo
                  <input type="file" id="avatar" name="avatar" class="d-none"
                         accept="image/png, image/jpeg, image/gif, image/webp" />
                </label>
                <small class="d-block text-muted">JPG, PNG, GIF or WebP, up to 2&nbsp;MB.</small>
                @error('avatar') <small class="d-block text-danger">{{ $message }}</small> @enderror
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name"
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', auth()->user()->name) }}" required />
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', auth()->user()->email) }}" required />
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="text-muted">This is the address you sign in with.</small>
              </div>

              <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input type="text" id="phone" name="phone"
                       class="form-control @error('phone') is-invalid @enderror"
                       value="{{ old('phone', auth()->user()->phone_number) }}" />
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary">
                  <i class="ti tabler-device-floppy me-1"></i>Save details
                </button>
              </div>
            </div>
          </form>

          <h6 class="mb-3">Change password</h6>

          <form method="POST" action="{{ route('admin.security.password') }}" class="mb-4">
            @csrf
            <div class="row g-3">
              <div class="col-12 form-password-toggle">
                <label class="form-label" for="current_password">Current Password</label>
                <div class="input-group input-group-merge @error('current_password') is-invalid @enderror">
                  <input type="password" id="current_password" name="current_password"
                         autocomplete="current-password"
                         class="form-control @error('current_password') is-invalid @enderror" required />
                  <span class="input-group-text cursor-pointer" role="button" tabindex="0"
                        aria-label="Show or hide the password">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
                @error('current_password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-sm-6 form-password-toggle">
                <label class="form-label" for="password">New Password</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="password" name="password" autocomplete="new-password"
                         class="form-control @error('password') is-invalid @enderror" required />
                  <span class="input-group-text cursor-pointer" role="button" tabindex="0"
                        aria-label="Show or hide the password">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
                @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
              </div>
              <div class="col-12 col-sm-6 form-password-toggle">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="password_confirmation" name="password_confirmation"
                         autocomplete="new-password" class="form-control" required />
                  <span class="input-group-text cursor-pointer" role="button" tabindex="0"
                        aria-label="Show or hide the password">
                    <i class="icon-base ti tabler-eye-off"></i>
                  </span>
                </div>
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

@section('page-script')
@include('_partials._password-toggle')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('avatar');
    const preview = document.getElementById('adminAvatarPreview');

    if (!input || !preview) return;

    // Shows the chosen file straight away. Without it the only sign the file
    // was taken is the form reloading after save.
    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file) return;

      preview.src = URL.createObjectURL(file);
      preview.onload = function () { URL.revokeObjectURL(preview.src); };
    });
  });
</script>
@endsection
