@extends('layouts/layoutMaster')

@section('title', 'Edit Profile')

@section('content')

{{-- Success/Error Messages --}}
@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if($errors->any())
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    @foreach($errors->all() as $error)
      <p class="mb-0">{{ $error }}</p>
    @endforeach
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row">
  {{-- Profile Details --}}
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header">
        <h5 class="card-title mb-0">Profile Details</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
          @csrf
          @method('PUT')
          
          {{-- Avatar Section --}}
          <div class="d-flex align-items-start align-items-sm-center gap-4 mb-6">
            <img src="{{ $user->avatar_url }}" alt="user-avatar" class="d-block w-px-100 h-px-100 rounded" id="uploadedAvatar" />
            <div class="button-wrapper">
              <label for="upload" class="btn btn-primary me-3 mb-4" tabindex="0">
                <span class="d-none d-sm-block">Upload new photo</span>
                <i class="icon-base ti tabler-upload d-block d-sm-none"></i>
                <input type="file" id="upload" name="avatar" class="account-file-input" hidden accept="image/png, image/jpeg" />
              </label>
              <button type="button" class="btn btn-label-secondary account-image-reset mb-4">
                <i class="icon-base ti tabler-refresh-dot d-block d-sm-none"></i>
                <span class="d-none d-sm-block">Reset</span>
              </button>
              <div class="text-muted">Allowed JPG, GIF or PNG. Max size of 2048K</div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Role</label>
              <input type="text" class="form-control" value="{{ $user->email === 'admin@playmint.com' ? 'Admin' : 'Member' }}" disabled />
            </div>
            <div class="col-md-6">
              <label class="form-label">Joined</label>
              <input type="text" class="form-control" value="{{ $user->created_at->format('d M Y, h:i A') }}" disabled />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Save Changes</button>
            <button type="reset" class="btn btn-label-secondary">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Change Password --}}
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Change Password</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('profile.password') }}">
          @csrf
          @method('PUT')
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="current_password">Current Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="current_password" name="current_password" class="form-control" placeholder="············" required />
                <span class="input-group-text cursor-pointer toggle-password"><i class="icon-base ti tabler-eye-off"></i></span>
              </div>
            </div>
          </div>
          <div class="row g-4 mt-1">
            <div class="col-md-6">
              <label class="form-label" for="password">New Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="password" name="password" class="form-control" placeholder="············" required />
                <span class="input-group-text cursor-pointer toggle-password"><i class="icon-base ti tabler-eye-off"></i></span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="password_confirmation">Confirm New Password</label>
              <div class="input-group input-group-merge">
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="············" required />
                <span class="input-group-text cursor-pointer toggle-password"><i class="icon-base ti tabler-eye-off"></i></span>
              </div>
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Change Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Avatar preview logic
  let accountUserImage = document.getElementById('uploadedAvatar');
  const fileInput = document.querySelector('.account-file-input'),
    resetFileInput = document.querySelector('.account-image-reset');

  if (accountUserImage) {
    const resetImage = accountUserImage.src;
    fileInput.onchange = () => {
      if (fileInput.files[0]) {
        accountUserImage.src = window.URL.createObjectURL(fileInput.files[0]);
      }
    };
    resetFileInput.onclick = () => {
      fileInput.value = '';
      accountUserImage.src = resetImage;
    };
  }

  document.querySelectorAll('.toggle-password').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const input = this.previousElementSibling;
      const icon = this.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('tabler-eye-off');
        icon.classList.add('tabler-eye');
      } else {
        input.type = 'password';
        icon.classList.remove('tabler-eye');
        icon.classList.add('tabler-eye-off');
      }
    });
  });
});
</script>
@endsection
