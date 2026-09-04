@php
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/blankLayout')

@section('title', 'Login')

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
    max-width: 420px;
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
  .form-control:focus, .form-select:focus {
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

    {{-- Right Column: Login Form --}}
    <div class="col-12 col-lg-5 col-xl-4 auth-form-container">
      <div class="auth-form-card">
        
        {{-- Logo --}}
        <div class="text-center mb-4">
          <img src="{{ asset('assets/auth/logo.png') }}" alt="Ready Route Logo" style="height: 68px; object-fit: contain;">
        </div>

        <h2 class="fw-bold text-center text-dark mb-4" style="font-size: 1.85rem;">Login</h2>

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

        <form id="formAuthentication" action="{{ route('login') }}" method="POST">
          @csrf

          {{-- Email Address --}}
          <div class="mb-4">
            <label for="email" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.9rem;">Email Address</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text bg-white border-end-0 text-primary px-3"><i class="ti tabler-mail fs-5"></i></span>
              <input
                type="email"
                class="form-control border-start-0 ps-1 py-2 @error('email') is-invalid @enderror"
                id="email"
                name="email"
                placeholder="yourname@gmail.com"
                value="{{ old('email') }}"
                required
                autofocus />
            </div>
          </div>

          {{-- Password --}}
          <div class="mb-4">
            <label for="password" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.9rem;">Password</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text bg-white border-end-0 text-primary px-3"><i class="ti tabler-lock fs-5"></i></span>
              <input
                type="password"
                id="password"
                name="password"
                class="form-control border-start-0 border-end-0 ps-1 py-2 @error('password') is-invalid @enderror"
                placeholder="••••••••"
                required />
              <span class="input-group-text bg-white border-start-0 text-danger cursor-pointer px-3" onclick="togglePassword('password', this)">
                <i class="ti tabler-eye-off fs-5"></i>
              </span>
            </div>
          </div>

          {{-- Remember Me & Forgot Password --}}
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember" name="remember" />
              <label class="form-check-label text-muted small" for="remember">Remember Me</label>
            </div>
            <a href="{{ route('password.request') }}" class="text-danger small fw-semibold text-decoration-none">Forgot Password?</a>
          </div>

          {{-- Login Submit Button --}}
          <div class="mb-4">
            <button class="btn btn-primary auth-btn-primary w-100" type="submit">Login</button>
          </div>

          {{-- Register Link --}}
          <p class="text-center text-muted small mb-0">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-primary fw-semibold text-decoration-none">Register</a>
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
