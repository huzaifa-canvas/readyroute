@extends('layouts/layoutMaster')

@section('title', 'Create New Trip - Dispatcher')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')

{{-- Alert Messages --}}
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

<form method="POST" action="{{ route('dispatcher.trip.store') }}" id="createTripForm">
  @csrf

  {{-- Top Header Bar --}}
  <div class="d-flex justify-content-between align-items-center mb-5">
    <h3 class="fw-bold mb-0">Create New Trip</h3>
    <div class="d-flex gap-3">
      <a href="{{ route('dispatcher.dashboard') }}" class="btn btn-outline-secondary px-4 py-2 fw-semibold">Cancel</a>
      <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">Save Trip</button>
    </div>
  </div>

  {{-- Quick Select Existing Client --}}
  @if($clients->count() > 0)
  <div class="card mb-4">
    <div class="card-body py-3">
      <div class="row align-items-center">
        <div class="col-md-3">
          <label for="client_select" class="form-label fw-bold mb-0"><i class="ti tabler-user-check me-1"></i>Search Existing Client:</label>
        </div>
        <div class="col-md-9">
          <select id="client_select" class="form-select select2">
            <option value="">-- Search or Choose a Client to Auto-fill --</option>
            @foreach($clients as $c)
              <option value="{{ $c->id }}" 
                      data-first_name="{{ \Illuminate\Support\Str::before($c->full_name, ' ') }}" 
                      data-last_name="{{ \Illuminate\Support\Str::after($c->full_name, ' ') ?: \Illuminate\Support\Str::before($c->full_name, ' ') }}"
                      data-phone="{{ $c->phone_number }}"
                      data-member_id="{{ $c->insurance_id }}"
                      data-address="{{ $c->home_address }}"
                      data-wheelchair="{{ $c->wheelchair_required ? 1 : 0 }}"
                      data-stretcher="{{ $c->stretcher_transport ? 1 : 0 }}"
                      data-bariatric="{{ $c->bariatric_vehicle ? 1 : 0 }}"
                      data-funding="{{ $c->funding_type }}">
                {{ $c->full_name }} ({{ $c->phone_number ?: 'No Phone' }})
              </option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
  </div>
  @endif

  <div class="row g-4">
    {{-- Left Column --}}
    <div class="col-lg-8">
      
      {{-- Card 1: Passenger Information --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-bold">Passenger Information</h5>
        </div>
        <div class="card-body">
          <input type="hidden" name="client_id" id="client_id" value="">
          
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
              <input type="text" id="first_name" name="first_name" class="form-control" placeholder="Ready Route Transport" value="{{ old('first_name') }}" required />
            </div>
            
            <div class="col-md-6">
              <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span></label>
              <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Ready Route Transport" value="{{ old('last_name') }}" required />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="phone_number">Phone Number</label>
              <input type="text" id="phone_number" name="phone_number" class="form-control" placeholder="Ready Route Transport" value="{{ old('phone_number') }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="member_id">Member ID</label>
              <input type="text" id="member_id" name="member_id" class="form-control" placeholder="Ready Route Transport" value="{{ old('member_id') }}" />
            </div>

            <div class="col-md-12 mt-4">
              <label class="form-label fw-semibold text-muted mb-2">Special Requirements</label>
              <div class="d-flex flex-wrap gap-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="req_wheelchair" name="req_wheelchair" value="1" {{ old('req_wheelchair') ? 'checked' : '' }}>
                  <label class="form-check-label" for="req_wheelchair">Wheelchair</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="req_stretcher" name="req_stretcher" value="1" {{ old('req_stretcher') ? 'checked' : '' }}>
                  <label class="form-check-label" for="req_stretcher">Stretcher</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="req_o2_tank" name="req_o2_tank" value="1" {{ old('req_o2_tank') ? 'checked' : '' }}>
                  <label class="form-check-label" for="req_o2_tank">O2 Tank</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="req_bariatric" name="req_bariatric" value="1" {{ old('req_bariatric') ? 'checked' : '' }}>
                  <label class="form-check-label" for="req_bariatric">Bariatric</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="req_no_steps" name="req_no_steps" value="1" {{ old('req_no_steps') ? 'checked' : '' }}>
                  <label class="form-check-label" for="req_no_steps">No Steps</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Card 2: Trip Details --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-bold">Trip Details</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="pickup_date">Pickup Date <span class="text-danger">*</span></label>
              <input type="text" id="pickup_date" name="pickup_date" class="form-control flatpickr-date" placeholder="YYYY-MM-DD" value="{{ old('pickup_date') }}" required />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="pickup_time">Pickup Time <span class="text-danger">*</span></label>
              <input type="text" id="pickup_time" name="pickup_time" class="form-control flatpickr-time" placeholder="HH:MM AM/PM" value="{{ old('pickup_time') }}" required />
            </div>

            <div class="col-md-6 position-relative">
              <label class="form-label" for="pickup_address">Pickup Address <span class="text-danger">*</span></label>
              <input type="text" id="pickup_address" name="pickup_address" class="form-control" placeholder="e.g. 142 oak ave, brooklyn, ny" value="{{ old('pickup_address') }}" autocomplete="off" required />
              <input type="hidden" name="pickup_lat" id="pickup_lat" value="{{ old('pickup_lat') }}">
              <input type="hidden" name="pickup_lng" id="pickup_lng" value="{{ old('pickup_lng') }}">
              <div id="pickup_suggestions" class="list-group position-absolute w-100 shadow-lg mt-1 bg-white border rounded" style="z-index: 1090; max-height: 220px; overflow-y: auto; display: none; background-color: #ffffff !important;"></div>
            </div>

            <div class="col-md-6 position-relative">
              <label class="form-label" for="dropoff_address">Drop-Off Address <span class="text-danger">*</span></label>
              <input type="text" id="dropoff_address" name="dropoff_address" class="form-control" placeholder="e.g. nyc medical center, new york, ny" value="{{ old('dropoff_address') }}" autocomplete="off" required />
              <input type="hidden" name="dropoff_lat" id="dropoff_lat" value="{{ old('dropoff_lat') }}">
              <input type="hidden" name="dropoff_lng" id="dropoff_lng" value="{{ old('dropoff_lng') }}">
              <input type="hidden" name="distance" id="distance" value="{{ old('distance') }}">
              <div id="dropoff_suggestions" class="list-group position-absolute w-100 shadow-lg mt-1 bg-white border rounded" style="z-index: 1090; max-height: 220px; overflow-y: auto; display: none; background-color: #ffffff !important;"></div>
            </div>

            <div class="col-md-12">
              <label class="form-label fw-semibold text-muted mb-2">Trip Type</label>
              <div class="row g-2">
                <div class="col-md-4">
                  <input type="radio" class="btn-check" name="trip_type" id="trip_one_way" value="one_way" {{ old('trip_type', 'one_way') == 'one_way' ? 'checked' : '' }}>
                  <label class="btn btn-outline-primary w-100 py-2 fw-semibold" for="trip_one_way">One-Way</label>
                </div>
                <div class="col-md-4">
                  <input type="radio" class="btn-check" name="trip_type" id="trip_round" value="round_trip" {{ old('trip_type') == 'round_trip' ? 'checked' : '' }}>
                  <label class="btn btn-outline-primary w-100 py-2 fw-semibold" for="trip_round">Round-Trip</label>
                </div>
                <div class="col-md-4">
                  <input type="radio" class="btn-check" name="trip_type" id="trip_recurring" value="recurring" {{ old('trip_type') == 'recurring' ? 'checked' : '' }}>
                  <label class="btn btn-outline-primary w-100 py-2 fw-semibold" for="trip_recurring">Recurring</label>
                </div>
              </div>
            </div>

            <div class="col-md-12">
              <label class="form-label" for="notes">Notes / Special Instructions</label>
              <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="e.g. client requires 10 min wait, non-emergency.">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>
      </div>

    </div>

    {{-- Right Column --}}
    <div class="col-lg-4">
      
      {{-- Assignment Card --}}
      <div class="card mb-4">
        <div class="card-header border-bottom mb-3">
          <h5 class="card-title mb-0 fw-bold">Assignment Information</h5>
        </div>
        <div class="card-body">
          
          {{-- Notice Box when disabled --}}
          <div id="assignmentNotice" class="alert alert-warning py-2 mb-3 small" style="display: block;">
            <i class="ti tabler-info-circle me-1"></i> Select <strong>Pickup Date</strong> and <strong>Time</strong> to enable Driver & Vehicle assignment.
          </div>

          {{-- Driver & Vehicle Container --}}
          <div id="assignmentContainer" style="opacity: 0.55; pointer-events: none; transition: all 0.3s ease;">
            <div class="mb-4">
              <label class="form-label fw-semibold" for="driver_id">Assign Driver</label>
              <select id="driver_id" name="driver_id" class="form-select" disabled>
                <option value="">-Auto-Assign-</option>
                @foreach($drivers as $driver)
                  <option value="{{ $driver->id }}" {{ old('driver_id') == $driver->id ? 'selected' : '' }}>
                    {{ $driver->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold" for="vehicle_id">Vehicle</label>
              <select id="vehicle_id" name="vehicle_id" class="form-select" disabled>
                <option value="">-Select Vehicle-</option>
                @foreach($vehicles as $vehicle)
                  <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                    {{ $vehicle->name }} - {{ $vehicle->make_model_year ?: 'Vehicle' }} ({{ $vehicle->number_plate ?: 'No Plate' }})
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="mb-2 mt-3">
            <label class="form-label fw-semibold" for="billing_type">Billing Type</label>
            <select id="billing_type" name="billing_type" class="form-select">
              <option value="medicaid" {{ old('billing_type') == 'medicaid' ? 'selected' : '' }}>Medicaid</option>
              <option value="medicare" {{ old('billing_type') == 'medicare' ? 'selected' : '' }}>Medicare</option>
              <option value="private" {{ old('billing_type') == 'private' ? 'selected' : '' }}>Private</option>
              <option value="insurance" {{ old('billing_type') == 'insurance' ? 'selected' : '' }}>Insurance</option>
            </select>
          </div>

        </div>
      </div>

      {{-- Trip Preview Card --}}
      <div class="card mb-4 bg-light-subtle">
        <div class="card-header border-bottom mb-2">
          <h5 class="card-title mb-0 fw-bold">Trip Preview</h5>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between border-bottom py-2">
            <span class="text-muted">Date</span>
            <span class="fw-semibold" id="prev_date">-</span>
          </div>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span class="text-muted">Time</span>
            <span class="fw-semibold" id="prev_time">-</span>
          </div>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span class="text-muted">Passenger</span>
            <span class="fw-semibold text-truncate ms-2" id="prev_passenger">-</span>
          </div>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span class="text-muted">Pickup</span>
            <span class="fw-semibold text-truncate ms-2" id="prev_pickup">-</span>
          </div>
          <div class="d-flex justify-content-between border-bottom py-2">
            <span class="text-muted">Distance</span>
            <span class="fw-semibold text-success ms-2" id="prev_distance">-</span>
          </div>
          <div class="d-flex justify-content-between py-2">
            <span class="text-muted">Driver</span>
            <span class="fw-semibold text-primary" id="prev_driver">Auto-Assign</span>
          </div>
        </div>
      </div>

    </div>
  </div>

</form>

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const pickupDate = document.getElementById('pickup_date');
  const pickupTime = document.getElementById('pickup_time');
  const driverSelect = document.getElementById('driver_id');
  const vehicleSelect = document.getElementById('vehicle_id');
  const assignmentContainer = document.getElementById('assignmentContainer');
  const assignmentNotice = document.getElementById('assignmentNotice');

  // Preview elements
  const prevDate = document.getElementById('prev_date');
  const prevTime = document.getElementById('prev_time');
  const prevPassenger = document.getElementById('prev_passenger');
  const prevPickup = document.getElementById('prev_pickup');
  const prevDriver = document.getElementById('prev_driver');

  const firstName = document.getElementById('first_name');
  const lastName = document.getElementById('last_name');
  const pickupAddress = document.getElementById('pickup_address');
  const clientSelect = document.getElementById('client_select');

  // Initialize Flatpickr Date Picker
  if (typeof flatpickr !== 'undefined') {
    flatpickr('#pickup_date', {
      dateFormat: 'Y-m-d',
      minDate: 'today',
      onChange: function(selectedDates, dateStr) {
        updateAssignmentState();
        updatePreview();
      }
    });

    // Initialize Flatpickr Time Picker
    flatpickr('#pickup_time', {
      enableTime: true,
      noCalendar: true,
      dateFormat: 'h:i K',
      onChange: function(selectedDates, dateStr) {
        updateAssignmentState();
        updatePreview();
      }
    });
  }

  // Check & toggle driver/vehicle availability based on Date & Time selection
  function updateAssignmentState() {
    const hasDate = pickupDate.value.trim() !== '';
    const hasTime = pickupTime.value.trim() !== '';

    if (hasDate && hasTime) {
      // Enable fields
      driverSelect.removeAttribute('disabled');
      vehicleSelect.removeAttribute('disabled');
      assignmentContainer.style.opacity = '1';
      assignmentContainer.style.pointerEvents = 'auto';
      assignmentNotice.style.display = 'none';
    } else {
      // Disable fields with light shade
      driverSelect.setAttribute('disabled', 'disabled');
      vehicleSelect.setAttribute('disabled', 'disabled');
      assignmentContainer.style.opacity = '0.55';
      assignmentContainer.style.pointerEvents = 'none';
      assignmentNotice.style.display = 'block';
    }
  }

  // Update Trip Preview card live
  function updatePreview() {
    prevDate.textContent = pickupDate.value ? pickupDate.value : '-';
    prevTime.textContent = pickupTime.value ? pickupTime.value : '-';
    
    const pName = (firstName.value + ' ' + lastName.value).trim();
    prevPassenger.textContent = pName ? pName : '-';
    prevPickup.textContent = pickupAddress.value ? pickupAddress.value : '-';

    if (driverSelect.value && !driverSelect.disabled) {
      const selectedOption = driverSelect.options[driverSelect.selectedIndex];
      prevDriver.textContent = selectedOption.text.trim();
    } else {
      prevDriver.textContent = 'Auto-Assign';
    }
  }

  pickupDate.addEventListener('change', function() {
    updateAssignmentState();
    updatePreview();
  });
  
  pickupTime.addEventListener('change', function() {
    updateAssignmentState();
    updatePreview();
  });

  firstName.addEventListener('input', updatePreview);
  lastName.addEventListener('input', updatePreview);
  pickupAddress.addEventListener('input', updatePreview);
  driverSelect.addEventListener('change', updatePreview);

  // Dynamic Select2 Loader to handle Vite async jQuery loading
  function initClientSelect2() {
    if (!window.jQuery) {
      setTimeout(initClientSelect2, 50);
      return;
    }
    
    const $ = window.jQuery;
    
    if (!$.fn || !$.fn.select2) {
      const script = document.createElement('script');
      script.src = "{{ asset('assets/vendor/libs/select2/select2.js') }}";
      script.onload = setupSelect2;
      document.head.appendChild(script);
    } else {
      setupSelect2();
    }
  }

  function setupSelect2() {
    const $ = window.jQuery;
    if ($('#client_select').length) {
      $('#client_select').select2({
        placeholder: '-- Search or Choose a Client to Auto-fill --',
        allowClear: true,
        width: '100%'
      }).on('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected && selected.value) {
          document.getElementById('client_id').value = selected.value;
          firstName.value = selected.dataset.first_name || '';
          lastName.value = selected.dataset.last_name || '';
          document.getElementById('phone_number').value = selected.dataset.phone || '';
          document.getElementById('member_id').value = selected.dataset.member_id || '';
          if (selected.dataset.address) {
            pickupAddress.value = selected.dataset.address;
          }

          if (selected.dataset.wheelchair == '1') document.getElementById('req_wheelchair').checked = true;
          if (selected.dataset.stretcher == '1') document.getElementById('req_stretcher').checked = true;
          if (selected.dataset.bariatric == '1') document.getElementById('req_bariatric').checked = true;

          if (selected.dataset.funding) {
            document.getElementById('billing_type').value = selected.dataset.funding;
          }

          updatePreview();
        }
      });
    }
  }

  initClientSelect2();

  // Distance Calculation (Haversine Formula in Miles)
  function calculateDistance() {
    const pLat = parseFloat(document.getElementById('pickup_lat').value);
    const pLng = parseFloat(document.getElementById('pickup_lng').value);
    const dLat = parseFloat(document.getElementById('dropoff_lat').value);
    const dLng = parseFloat(document.getElementById('dropoff_lng').value);
    const prevDistance = document.getElementById('prev_distance');
    const distanceInput = document.getElementById('distance');

    if (!isNaN(pLat) && !isNaN(pLng) && !isNaN(dLat) && !isNaN(dLng)) {
      const R = 3958.8; // Radius of Earth in Miles
      const radLat1 = pLat * Math.PI / 180;
      const radLat2 = dLat * Math.PI / 180;
      const deltaLat = (dLat - pLat) * Math.PI / 180;
      const deltaLng = (dLng - pLng) * Math.PI / 180;

      const a = Math.sin(deltaLat/2) * Math.sin(deltaLat/2) +
                Math.cos(radLat1) * Math.cos(radLat2) *
                Math.sin(deltaLng/2) * Math.sin(deltaLng/2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
      const dist = (R * c).toFixed(2);

      distanceInput.value = dist;
      prevDistance.textContent = dist + ' Miles';
    } else {
      distanceInput.value = '';
      prevDistance.textContent = '-';
    }
  }

  // Photon Address Autocomplete Function
  function initAddressAutocomplete(inputId, latId, lngId, suggestionsId) {
    const input = document.getElementById(inputId);
    const latInput = document.getElementById(latId);
    const lngInput = document.getElementById(lngId);
    const suggestionsBox = document.getElementById(suggestionsId);
    let debounceTimer;

    input.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      const query = this.value.trim();

      if (query.length < 3) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        return;
      }

      debounceTimer = setTimeout(() => {
        fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=5`)
          .then(res => res.json())
          .then(data => {
            suggestionsBox.innerHTML = '';
            if (data.features && data.features.length > 0) {
              data.features.forEach(feature => {
                const props = feature.properties;
                const coords = feature.geometry.coordinates; // [lng, lat]
                
                let parts = [];
                if (props.name) parts.push(props.name);
                if (props.street && props.street !== props.name) parts.push(props.street);
                if (props.city) parts.push(props.city);
                if (props.state) parts.push(props.state);
                if (props.country) parts.push(props.country);
                
                const addressStr = parts.join(', ');
                
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action py-2 px-3 small text-start border-bottom';
                item.style.backgroundColor = '#ffffff';
                item.style.color = '#333333';
                item.innerHTML = `<i class="ti tabler-map-pin text-primary me-2"></i> <strong>${props.name || ''}</strong> ${parts.slice(1).join(', ')}`;
                
                item.addEventListener('click', function(e) {
                  e.preventDefault();
                  input.value = addressStr;
                  lngInput.value = coords[0];
                  latInput.value = coords[1];
                  suggestionsBox.style.display = 'none';
                  
                  calculateDistance();
                  updatePreview();
                });
                
                suggestionsBox.appendChild(item);
              });
              suggestionsBox.style.display = 'block';
            } else {
              suggestionsBox.style.display = 'none';
            }
          })
          .catch(() => {
            suggestionsBox.style.display = 'none';
          });
      }, 300);
    });

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
      if (!input.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.style.display = 'none';
      }
    });
  }

  // Initialize Autocomplete on Pickup and Dropoff inputs
  initAddressAutocomplete('pickup_address', 'pickup_lat', 'pickup_lng', 'pickup_suggestions');
  initAddressAutocomplete('dropoff_address', 'dropoff_lat', 'dropoff_lng', 'dropoff_suggestions');

  // Initial check
  updateAssignmentState();
  updatePreview();
});
</script>
@endsection
