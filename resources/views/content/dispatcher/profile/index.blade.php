@extends('layouts/layoutMaster')

@section('title', 'My Profile & Security')

@section('content')
<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Account /</span> Profile & Security
</h4>

<div class="row">
  <div class="col-md-12">

    {{-- Alert Messages --}}
    @if(session('success_profile'))
      <div class="alert alert-success alert-dismissible mb-4" role="alert">
        <i class="ti tabler-circle-check me-2"></i>{{ session('success_profile') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif

    @if(session('success_password'))
      <div class="alert alert-success alert-dismissible mb-4" role="alert">
        <i class="ti tabler-circle-check me-2"></i>{{ session('success_password') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif

    @if(session('success_2fa'))
      <div class="alert alert-success alert-dismissible mb-4" role="alert">
        <i class="ti tabler-circle-check me-2"></i>{{ session('success_2fa') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif

    {{-- CARD 1: PROFILE DETAILS FORM --}}
    <div class="card mb-4 shadow-sm border-0">
      <h5 class="card-header border-bottom fw-bold"><i class="ti tabler-user me-2 text-primary"></i>Profile Details</h5>
      
      <form id="formAccountSettings" method="POST" action="{{ route('dispatcher.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card-body">
          {{-- Avatar Upload Section --}}
          <div class="d-flex align-items-start align-items-sm-center gap-4">
            <img
              src="{{ $user->avatar ? asset($user->avatar) : asset('assets/img/avatars/1.png') }}"
              alt="user-avatar"
              class="d-block w-px-100 h-px-100 rounded"
              id="uploadedAvatar" style="object-fit: cover;" />
            <div class="button-wrapper">
              <label for="upload" class="btn btn-primary me-2 mb-3" tabindex="0">
                <span class="d-none d-sm-block">Upload new photo</span>
                <i class="ti tabler-upload d-block d-sm-none"></i>
                <input
                  type="file"
                  id="upload"
                  name="avatar"
                  class="account-file-input"
                  hidden
                  accept="image/png, image/jpeg, image/gif, image/svg+xml"
                  onchange="previewAvatar(this)" />
              </label>
              <button type="button" class="btn btn-label-secondary account-image-reset mb-3" onclick="resetAvatar()">
                <i class="ti tabler-refresh-dot d-block d-sm-none"></i>
                <span class="d-none d-sm-block">Reset</span>
              </button>
              <div class="text-muted small">Allowed JPG, GIF or PNG. Max size of 2MB</div>
            </div>
          </div>
        </div>

        <div class="card-body border-top pt-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input
                class="form-control @error('name') is-invalid @enderror"
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $user->name) }}"
                required />
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
              <input
                class="form-control @error('email') is-invalid @enderror"
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $user->email) }}"
                required />
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label for="phone" class="form-label fw-semibold">Phone Number</label>
              <input
                type="text"
                id="phone"
                name="phone"
                class="form-control @error('phone') is-invalid @enderror"
                placeholder="+1 (555) 000-0000"
                value="{{ old('phone', $user->phone_number ?? $user->phone) }}" />
              @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Role</label>
              <input
                type="text"
                class="form-control text-capitalize bg-light"
                value="{{ $user->role ?: 'Dispatcher' }}"
                readonly />
            </div>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-primary me-2">Save Profile Changes</button>
            <button type="reset" class="btn btn-label-secondary">Cancel</button>
          </div>
        </div>
      </form>
    </div>

    {{-- CARD 2: CHANGE PASSWORD FORM --}}
    <div class="card mb-4 shadow-sm border-0">
      <h5 class="card-header border-bottom fw-bold"><i class="ti tabler-lock me-2 text-warning"></i>Change Password</h5>
      
      <form id="formChangePassword" method="POST" action="{{ route('dispatcher.profile.password') }}">
        @csrf
        @method('PUT')

        <div class="card-body">
          <div class="row g-3">
            {{-- Current Password --}}
            <div class="col-md-6 form-password-toggle">
              <label class="form-label fw-semibold" for="current_password">Current Password <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge">
                <input
                  class="form-control @error('current_password') is-invalid @enderror"
                  type="password"
                  name="current_password"
                  id="current_password"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  required />
                <span class="input-group-text cursor-pointer" onclick="togglePasswordVisibility('current_password', this)">
                  <i class="ti tabler-eye-off"></i>
                </span>
                @error('current_password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>

          <div class="row g-3 mt-1">
            {{-- New Password --}}
            <div class="col-md-6 form-password-toggle">
              <label class="form-label fw-semibold" for="new_password">New Password <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge">
                <input
                  class="form-control @error('new_password') is-invalid @enderror"
                  type="password"
                  id="new_password"
                  name="new_password"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  required />
                <span class="input-group-text cursor-pointer" onclick="togglePasswordVisibility('new_password', this)">
                  <i class="ti tabler-eye-off"></i>
                </span>
                @error('new_password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- Confirm New Password --}}
            <div class="col-md-6 form-password-toggle">
              <label class="form-label fw-semibold" for="new_password_confirmation">Confirm New Password <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge">
                <input
                  class="form-control"
                  type="password"
                  name="new_password_confirmation"
                  id="new_password_confirmation"
                  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                  required />
                <span class="input-group-text cursor-pointer" onclick="togglePasswordVisibility('new_password_confirmation', this)">
                  <i class="ti tabler-eye-off"></i>
                </span>
              </div>
            </div>
          </div>

          {{-- Password Requirements Hint --}}
          <div class="col-12 mt-4">
            <h6 class="fw-bold mb-2">Password Requirements:</h6>
            <ul class="ps-3 mb-0 small text-muted">
              <li class="mb-1">Minimum 8 characters long - the more, the better</li>
              <li class="mb-1">At least one lowercase character</li>
              <li>At least one number, symbol, or whitespace character</li>
            </ul>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-warning me-2 fw-semibold">Update Password</button>
            <button type="reset" class="btn btn-label-secondary">Cancel</button>
          </div>
        </div>
      </form>
    </div>

    {{-- CARD 3: TWO-FACTOR AUTHENTICATION (2FA) --}}
    @php
      $is2faActive = $user->getMeta('two_factor_enabled', false) || !is_null($user->two_factor_confirmed_at);
    @endphp
    <div class="card mb-4 shadow-sm border-0">
      <h5 class="card-header border-bottom fw-bold"><i class="ti tabler-shield-check me-2 text-success"></i>Two-Factor Authentication (2FA)</h5>
      <div class="card-body pt-4">
        @if($is2faActive)
          <div class="p-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background-color: #e8f5e9; border: 1px solid #c8e6c9;">
            <div>
              <h6 class="fw-bold text-success mb-1 fs-5"><i class="ti tabler-circle-check me-2"></i>Authenticator App Enabled</h6>
              <small class="text-muted d-block">Your account is highly secure.</small>
            </div>
            <form action="{{ route('dispatcher.profile.2fa') }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill px-4 fw-semibold" onclick="return confirm('Are you sure you want to disable 2FA for your account?')">Disable 2FA</button>
            </form>
          </div>
        @else
          <div class="p-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-3 bg-label-secondary border">
            <div>
              <h6 class="fw-bold text-heading mb-1 fs-5"><i class="ti tabler-shield-x me-2 text-warning"></i>Two-Factor Authentication Disabled</h6>
              <small class="text-muted d-block">Add an extra layer of security to your account.</small>
            </div>
            <form action="{{ route('dispatcher.profile.2fa') }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold">Enable 2FA</button>
            </form>
          </div>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection

@section('page-script')
<script>
const originalAvatarSrc = "{{ $user->avatar ? asset($user->avatar) : asset('assets/img/avatars/1.png') }}";

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('uploadedAvatar').src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
  }
}

function resetAvatar() {
  document.getElementById('upload').value = '';
  document.getElementById('uploadedAvatar').src = originalAvatarSrc;
}

function togglePasswordVisibility(inputId, iconElement) {
  const input = document.getElementById(inputId);
  const icon = iconElement.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('tabler-eye-off');
    icon.classList.add('tabler-eye');
  } else {
    input.type = 'password';
    icon.classList.remove('tabler-eye');
    icon.classList.add('tabler-eye-off');
  }
}
</script>
@endsection
