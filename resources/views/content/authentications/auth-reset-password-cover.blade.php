@php
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Reset Password')

@section('page-style')
<style>
  .auth-cover-wrapper {
    min-height: 100vh;
    display: flex;
    background: #ffffff;
  }
  .auth-video-container {
    position: relative;
    overflow: hidden;
    background: #000000;
  }
  .auth-video-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.20);
    z-index: 2;
  }
  .auth-video-el {
    position: absolute;
    top: 50%;
    left: 50%;
    min-width: 100%;
    min-height: 100%;
    width: auto;
    height: auto;
    transform: translate(-50%, -50%);
    object-fit: cover;
    z-index: 1;
  }
  .auth-form-container {
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2.5rem;
  }
  .auth-form-card {
    width: 100%;
    max-width: 440px;
  }
  .auth-btn-primary {
    background-color: #0038ff !important;
    border-color: #0038ff !important;
    border-radius: 8px !important;
    font-weight: 700 !important;
    padding: 0.75rem 1rem !important;
    font-size: 1rem !important;
  }
  .auth-btn-primary:hover {
    background-color: #002cd6 !important;
    border-color: #002cd6 !important;
  }
  .form-control:focus {
    border-color: #0038ff;
    box-shadow: 0 0 0 0.2rem rgba(0, 56, 255, 0.15);
  }
</style>
@endsection

@section('content')
<div class="container-fluid p-0">
  <div class="row g-0 auth-cover-wrapper">
    
    {{-- Left Column: Video Background Cover --}}
    <div class="col-lg-7 col-xl-8 d-none d-lg-block auth-video-container">
      <div class="auth-video-overlay"></div>
      <video class="auth-video-el" autoplay loop muted playsinline>
        <source src="{{ asset('assets/auth/cover.mp4') }}" type="video/mp4">
      </video>
    </div>

    {{-- Right Column: Reset Password Form --}}
    <div class="col-12 col-lg-5 col-xl-4 auth-form-container">
      <div class="auth-form-card">
        
        {{-- Logo --}}
        <div class="text-center mb-4">
          <img src="{{ asset('assets/auth/logo.png') }}" alt="Ready Route Logo" style="height: 68px; object-fit: contain;">
        </div>

        <h2 class="fw-bold text-center text-dark mb-2" style="font-size: 1.85rem;">Reset Password 🔑</h2>
        <p class="text-center text-muted small mb-4">Your new password must be different from previously used passwords</p>

        {{-- Error Alerts --}}
        @if($errors->any())
          <div class="alert alert-danger alert-dismissible mb-4" role="alert">
            <ul class="mb-0 ps-3 small">
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif

        <form id="formAuthentication" action="{{ route('password.update') }}" method="POST">
          @csrf
          <input type="hidden" name="token" value="{{ $request->route('token') }}">

          {{-- Email Address --}}
          <div class="mb-3">
            <label for="email" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.9rem;">Email Address</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text bg-white border-end-0 text-primary px-3"><i class="ti tabler-mail fs-5"></i></span>
              <input
                type="email"
                class="form-control border-start-0 ps-1 py-2 @error('email') is-invalid @enderror"
                id="email"
                name="email"
                placeholder="yourname@gmail.com"
                value="{{ old('email', $request->email) }}"
                required
                readonly />
            </div>
          </div>

          {{-- New Password --}}
          <div class="mb-3">
            <label for="password" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.9rem;">New Password</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text bg-white border-end-0 text-primary px-3"><i class="ti tabler-lock fs-5"></i></span>
              <input
                type="password"
                id="password"
                name="password"
                class="form-control border-start-0 border-end-0 ps-1 py-2 @error('password') is-invalid @enderror"
                placeholder="••••••••"
                required
                autofocus />
              <span class="input-group-text bg-white border-start-0 text-danger cursor-pointer px-3" onclick="togglePassword('password', this)">
                <i class="ti tabler-eye-off fs-5"></i>
              </span>
            </div>
          </div>

          {{-- Confirm Password --}}
          <div class="mb-4">
            <label for="password_confirmation" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.9rem;">Confirm New Password</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text bg-white border-end-0 text-primary px-3"><i class="ti tabler-lock-check fs-5"></i></span>
              <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control border-start-0 border-end-0 ps-1 py-2"
                placeholder="••••••••"
                required />
              <span class="input-group-text bg-white border-start-0 text-danger cursor-pointer px-3" onclick="togglePassword('password_confirmation', this)">
                <i class="ti tabler-eye-off fs-5"></i>
              </span>
            </div>
          </div>

          {{-- Submit Button --}}
          <div class="mb-4">
            <button class="btn btn-primary auth-btn-primary w-100" type="submit">Set New Password</button>
          </div>

          {{-- Back to Login --}}
          <p class="text-center mb-0">
            <a href="{{ route('login') }}" class="text-primary fw-semibold text-decoration-none">
              <i class="ti tabler-chevron-left me-1"></i>Back to login
            </a>
          </p>
        </form>

      </div>
    </div>

  </div>
</div>
@endsection

@section('page-script')
<script>
function togglePassword(inputId, iconContainer) {
  const input = document.getElementById(inputId);
  const icon = iconContainer.querySelector('i');
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
