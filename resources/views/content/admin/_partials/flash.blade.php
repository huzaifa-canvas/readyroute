{{--
  Shared flash messages for the admin and dispatcher screens.

  Every page was repeating the same two alert blocks; this keeps the markup in
  one place so the wording and icons stay consistent as screens are added.
--}}
@if(session('success'))
  <div class="alert alert-success alert-dismissible d-flex align-items-center mb-4" role="alert">
    <i class="ti tabler-circle-check me-2 flex-shrink-0"></i>
    <span>{{ session('success') }}</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible d-flex align-items-center mb-4" role="alert">
    <i class="ti tabler-alert-circle me-2 flex-shrink-0"></i>
    <span>{{ session('error') }}</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('warning'))
  <div class="alert alert-warning alert-dismissible d-flex align-items-center mb-4" role="alert">
    <i class="ti tabler-alert-triangle me-2 flex-shrink-0"></i>
    <span>{{ session('warning') }}</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    <div class="d-flex align-items-center mb-1">
      <i class="ti tabler-alert-circle me-2 flex-shrink-0"></i>
      <strong>Please check the form</strong>
    </div>
    <ul class="mb-0 ps-4">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
