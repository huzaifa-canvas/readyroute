@extends('layouts/layoutMaster')

@section('title', 'Edit Trip #' . $trip->id . ' - Dispatcher')

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

{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="{{ route('dispatcher.trip.details', $trip->id) }}" class="btn btn-icon btn-label-secondary me-2">
      <i class="ti tabler-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Edit Trip #{{ $trip->id }}</h4>
  </div>
</div>

<form action="{{ route('dispatcher.trip.update', $trip->id) }}" method="POST" id="editTripForm">
  @csrf
  @method('PUT')

  <div class="row g-4">
    {{-- Left Column: Main Form Inputs --}}
    <div class="col-lg-8">
      <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3"><i class="ti tabler-user me-2 text-primary"></i>Passenger Info</h5>

          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label" for="client_id">Select Existing Client (Optional)</label>
              <select id="client_id" name="client_id" class="form-select select2">
                <option value="">-- Manual Entry / New Passenger --</option>
                @foreach($clients as $client)
                  <option value="{{ $client->id }}" 
                    data-first_name="{{ \Illuminate\Support\Str::before($client->full_name, ' ') }}" 
                    data-last_name="{{ \Illuminate\Support\Str::after($client->full_name, ' ') ?: \Illuminate\Support\Str::before($client->full_name, ' ') }}"
                    data-phone="{{ $client->phone_number }}"
                    data-member="{{ $client->insurance_id }}"
                    data-address="{{ $client->home_address }}"
                    data-wheelchair="{{ $client->wheelchair_required ? 1 : 0 }}"
                    data-stretcher="{{ $client->stretcher_transport ? 1 : 0 }}"
                    data-bariatric="{{ $client->bariatric_vehicle ? 1 : 0 }}"
                    data-billing="{{ $client->funding_type }}"
                    {{ old('client_id', $trip->client_id) == $client->id ? 'selected' : '' }}>
                    {{ $client->full_name }} ({{ $client->phone_number ?: 'No Phone' }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
              <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $trip->first_name) }}" required />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span></label>
              <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $trip->last_name) }}" required />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="phone_number">Phone Number</label>
              <input type="text" id="phone_number" name="phone_number" class="form-control" value="{{ old('phone_number', $trip->phone_number) }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="member_id">Member ID</label>
              <input type="text" id="member_id" name="member_id" class="form-control" value="{{ old('member_id', $trip->member_id) }}" />
            </div>
          </div>

          <hr class="my-4" />

          {{-- Special Requirements Checkboxes --}}
          <h6 class="fw-semibold mb-3">Special Requirements</h6>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="req_wheelchair" id="req_wheelchair" value="1" {{ old('req_wheelchair', $trip->req_wheelchair) ? 'checked' : '' }} />
              <label class="form-check-label" for="req_wheelchair"><i class="ti tabler-wheelchair me-1"></i>Wheelchair</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="req_stretcher" id="req_stretcher" value="1" {{ old('req_stretcher', $trip->req_stretcher) ? 'checked' : '' }} />
              <label class="form-check-label" for="req_stretcher"><i class="ti tabler-bed me-1"></i>Stretcher</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="req_o2_tank" id="req_o2_tank" value="1" {{ old('req_o2_tank', $trip->req_o2_tank) ? 'checked' : '' }} />
              <label class="form-check-label" for="req_o2_tank"><i class="ti tabler-asset me-1"></i>O2 Tank</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="req_bariatric" id="req_bariatric" value="1" {{ old('req_bariatric', $trip->req_bariatric) ? 'checked' : '' }} />
              <label class="form-check-label" for="req_bariatric">Bariatric</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="req_no_steps" id="req_no_steps" value="1" {{ old('req_no_steps', $trip->req_no_steps) ? 'checked' : '' }} />
              <label class="form-check-label" for="req_no_steps">No Steps</label>
            </div>
          </div>

          <hr class="my-4" />

          <h5 class="fw-bold mb-3"><i class="ti tabler-map-pin me-2 text-primary"></i>Trip Details</h5>
          
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="pickup_date">Pickup Date <span class="text-danger">*</span></label>
              <input type="text" id="pickup_date" name="pickup_date" class="form-control flatpickr-date" value="{{ old('pickup_date', $trip->pickup_date ? $trip->pickup_date->format('Y-m-d') : '') }}" required />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="pickup_time">Pickup Time <span class="text-danger">*</span></label>
              <input type="text" id="pickup_time" name="pickup_time" class="form-control flatpickr-time" value="{{ old('pickup_time', $trip->pickup_time ? \Carbon\Carbon::parse($trip->pickup_time)->format('h:i A') : '') }}" required />
            </div>

            <div class="col-md-6 position-relative">
              <label class="form-label" for="pickup_address">Pickup Address <span class="text-danger">*</span></label>
              <input type="text" id="pickup_address" name="pickup_address" class="form-control" value="{{ old('pickup_address', $trip->pickup_address) }}" autocomplete="off" required />
              <input type="hidden" name="pickup_lat" id="pickup_lat" value="{{ old('pickup_lat', $trip->pickup_lat) }}">
              <input type="hidden" name="pickup_lng" id="pickup_lng" value="{{ old('pickup_lng', $trip->pickup_lng) }}">
              <div id="pickup_suggestions" class="list-group position-absolute w-100 shadow-lg mt-1 bg-white border rounded" style="z-index: 1090; max-height: 220px; overflow-y: auto; display: none; background-color: #ffffff !important;"></div>
            </div>

            <div class="col-md-6 position-relative">
              <label class="form-label" for="dropoff_address">Drop-Off Address <span class="text-danger">*</span></label>
              <input type="text" id="dropoff_address" name="dropoff_address" class="form-control" value="{{ old('dropoff_address', $trip->dropoff_address) }}" autocomplete="off" required />
              <input type="hidden" name="dropoff_lat" id="dropoff_lat" value="{{ old('dropoff_lat', $trip->dropoff_lat) }}">
              <input type="hidden" name="dropoff_lng" id="dropoff_lng" value="{{ old('dropoff_lng', $trip->dropoff_lng) }}">
              <input type="hidden" name="distance" id="distance" value="{{ old('distance', $trip->distance) }}">
              <div id="dropoff_suggestions" class="list-group position-absolute w-100 shadow-lg mt-1 bg-white border rounded" style="z-index: 1090; max-height: 220px; overflow-y: auto; display: none; background-color: #ffffff !important;"></div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="status">Trip Status <span class="text-danger">*</span></label>
              <select id="status" name="status" class="form-select" required>
                <option value="scheduled" {{ old('status', $trip->status) == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                <option value="in_progress" {{ old('status', $trip->status) == 'in_progress' ? 'selected' : '' }}>In Progress / En Route</option>
                <option value="completed" {{ old('status', $trip->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ old('status', $trip->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="trip_type">Trip Type <span class="text-danger">*</span></label>
              <select id="trip_type" name="trip_type" class="form-select" required>
                <option value="one_way" {{ old('trip_type', $trip->trip_type) == 'one_way' ? 'selected' : '' }}>One-Way</option>
                <option value="round_trip" {{ old('trip_type', $trip->trip_type) == 'round_trip' ? 'selected' : '' }}>Round-Trip</option>
                <option value="recurring" {{ old('trip_type', $trip->trip_type) == 'recurring' ? 'selected' : '' }}>Recurring</option>
              </select>
            </div>

            <div class="col-md-12">
              <label class="form-label" for="notes">Notes / Special Instructions</label>
              <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $trip->notes) }}</textarea>
            </div>
          </div>

        </div>
      </div>
    </div>

    {{-- Right Column: Assignment & Actions --}}
    <div class="col-lg-4">
      <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
          <h5 class="fw-bold mb-3"><i class="ti tabler-steering-wheel me-2 text-primary"></i>Assignment</h5>

          <div class="mb-3">
            <label class="form-label" for="driver_id">Driver</label>
            <select id="driver_id" name="driver_id" class="form-select">
              <option value="">-- Unassigned --</option>
              @foreach($drivers as $driver)
                <option value="{{ $driver->id }}" {{ old('driver_id', $trip->driver_id) == $driver->id ? 'selected' : '' }}>
                  {{ $driver->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="vehicle_id">Vehicle</label>
            <select id="vehicle_id" name="vehicle_id" class="form-select">
              <option value="">-- Unassigned --</option>
              @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $trip->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                  {{ $vehicle->name }} ({{ $vehicle->license_plate ?: 'No Plate' }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="billing_type">Billing Type</label>
            <select id="billing_type" name="billing_type" class="form-select">
              <option value="medicare" {{ old('billing_type', $trip->billing_type) == 'medicare' ? 'selected' : '' }}>Medicare</option>
              <option value="private_pay" {{ old('billing_type', $trip->billing_type) == 'private_pay' ? 'selected' : '' }}>Private Pay</option>
              <option value="insurance" {{ old('billing_type', $trip->billing_type) == 'insurance' ? 'selected' : '' }}>Insurance</option>
            </select>
          </div>

          <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="ti tabler-check me-1"></i> Save Changes
            </button>
            <a href="{{ route('dispatcher.trip.details', $trip->id) }}" class="btn btn-label-secondary">
              Cancel
            </a>
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
    if ($('#client_id').length) {
      $('#client_id').select2({
        placeholder: '-- Search or Select Existing Client --',
        allowClear: true,
        width: '100%'
      }).on('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected && selected.value) {
          document.getElementById('first_name').value = selected.dataset.first_name || '';
          document.getElementById('last_name').value  = selected.dataset.last_name || '';
          document.getElementById('phone_number').value = selected.dataset.phone || '';
          document.getElementById('member_id').value    = selected.dataset.member || '';
          if (selected.dataset.address) {
            document.getElementById('pickup_address').value = selected.dataset.address;
          }

          if (selected.dataset.wheelchair == '1') document.getElementById('req_wheelchair').checked = true;
          if (selected.dataset.stretcher == '1') document.getElementById('req_stretcher').checked = true;
          if (selected.dataset.bariatric == '1') document.getElementById('req_bariatric').checked = true;

          if (selected.dataset.billing) {
            document.getElementById('billing_type').value = selected.dataset.billing;
          }
        }
      });
    }
  }

  initClientSelect2();

  if (typeof flatpickr !== 'undefined') {
    flatpickr('.flatpickr-date', {
      dateFormat: 'Y-m-d',
      minDate: 'today'
    });

    flatpickr('.flatpickr-time', {
      enableTime: true,
      noCalendar: true,
      dateFormat: 'h:i K'
    });
  }

  // Distance Calculation (Haversine Formula in Miles)
  function calculateDistance() {
    const pLat = parseFloat(document.getElementById('pickup_lat').value);
    const pLng = parseFloat(document.getElementById('pickup_lng').value);
    const dLat = parseFloat(document.getElementById('dropoff_lat').value);
    const dLng = parseFloat(document.getElementById('dropoff_lng').value);
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
      distanceInput.value = (R * c).toFixed(2);
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

    document.addEventListener('click', function(e) {
      if (!input.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.style.display = 'none';
      }
    });
  }

  initAddressAutocomplete('pickup_address', 'pickup_lat', 'pickup_lng', 'pickup_suggestions');
  initAddressAutocomplete('dropoff_address', 'dropoff_lat', 'dropoff_lng', 'dropoff_suggestions');
});
</script>
@endsection
