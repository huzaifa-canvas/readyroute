@extends('layouts/layoutMaster')

@section('title', 'Trip Calendar')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/fullcalendar/fullcalendar.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-calendar.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/fullcalendar/fullcalendar.js') }}"></script>
@endsection

@section('content')

<div class="card app-calendar-wrapper">
  <div class="row g-0">

    {{-- ============== LEFT SIDEBAR ============== --}}
    <div class="col app-calendar-sidebar border-end" id="app-calendar-sidebar">

      {{-- Add Trip Button --}}
      <div class="border-bottom p-6 my-sm-0 mb-4">
        <a href="{{ route('dispatcher.trip.create') }}" class="btn btn-primary w-100">
          <i class="icon-base ti tabler-plus icon-16px me-2"></i>
          <span class="align-middle">Create Trip</span>
        </a>
      </div>

      {{-- Event Filters Section --}}
      <div class="px-6 pt-4 pb-2">
        <div class="mb-4">
          <h5 class="mb-1">Event Filters</h5>
          <small class="text-muted">Filter by Driver</small>
        </div>

        {{-- View All --}}
        <div class="form-check form-check-secondary mb-4 ms-2">
          <input class="form-check-input select-all" type="checkbox" id="filter-all" data-value="all" checked />
          <label class="form-check-label" for="filter-all">View All</label>
        </div>

        <div class="app-calendar-events-filter">
          {{-- Unassigned --}}
          <div class="form-check form-check-secondary mb-3 ms-2">
            <input class="form-check-input input-filter"
              type="checkbox"
              id="filter-unassigned"
              data-value="unassigned"
              checked />
            <label class="form-check-label" for="filter-unassigned">Unassigned</label>
          </div>

          @php
            $vuexyCheckClasses = ['form-check-primary', 'form-check-success', 'form-check-info', 'form-check-warning', 'form-check-danger'];
            $ci = 0;
          @endphp

          @foreach($drivers as $driver)
            @php 
              $cClass = $vuexyCheckClasses[$ci % count($vuexyCheckClasses)]; 
              $ci++; 
            @endphp
            <div class="form-check {{ $cClass }} mb-3 ms-2">
              <input class="form-check-input input-filter"
                type="checkbox"
                id="filter-driver-{{ $driver->id }}"
                data-value="{{ $driver->id }}"
                checked />
              <label class="form-check-label" for="filter-driver-{{ $driver->id }}">
                {{ $driver->name }}
              </label>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Driver Color Legend --}}
      @if($drivers->count() > 0)
      <hr class="mx-4 mt-2 mb-4" />
      <div class="px-6 pb-4">
        <small class="text-muted fw-semibold text-uppercase d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.05rem;">Color Legend</small>
        <div class="mb-3">
          <span class="badge rounded-pill me-2 bg-label-secondary">●</span>
          <small>Unassigned</small>
        </div>
        @php $ci2 = 0; @endphp
        @foreach($drivers as $driver)
          @php 
            $cBadgeName = ['primary','success','info','warning','danger'][$ci2 % 5];
            $ci2++; 
          @endphp
          <div class="mb-3">
            <span class="badge rounded-pill me-2 bg-label-{{ $cBadgeName }}">●</span>
            <small>{{ $driver->name }}</small>
          </div>
        @endforeach
      </div>
      @endif

    </div>
    {{-- ============== /LEFT SIDEBAR ============== --}}

    {{-- ============== MAIN CALENDAR ============== --}}
    <div class="col app-calendar-content">
      <div class="card shadow-none border-0">
        <div class="card-body pb-0 position-relative" style="min-height: 500px;">
          
          {{-- Vuexy Translucent Loading Overlay --}}
          <div id="calendarLoader" class="position-absolute top-0 start-0 w-100 h-100 d-none flex-column align-items-center justify-content-center rounded" style="z-index: 50; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(2px);">
            <div class="spinner-border text-primary mb-2" role="status" style="width: 2.2rem; height: 2.2rem;">
              <span class="visually-hidden">Loading...</span>
            </div>
            <small class="fw-bold text-primary">Updating Calendar...</small>
          </div>

          <div id="tripCalendar"></div>
        </div>
      </div>
      <div class="app-overlay"></div>
    </div>
    {{-- ============== /MAIN CALENDAR ============== --}}

  </div>
</div>

{{-- ============== TRIP DETAILS MODAL ============== --}}
<div class="modal fade" id="tripDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header" id="modalHeaderBar" style="border-bottom: 3px solid #696cff;">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded-circle bg-label-primary" id="modalAvatarInitial">T</span>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" id="modalTripTitle">Trip Details</h5>
            <small class="text-muted" id="modalTripDateTime">-</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <span class="badge" id="modalTripStatus">SCHEDULED</span>
          <span class="badge bg-label-secondary" id="modalTripType">One Way</span>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-12">
            <div class="d-flex align-items-start gap-3 p-3 rounded" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05);">
              <i class="ti tabler-user text-primary mt-1"></i>
              <div>
                <label class="text-muted small d-block mb-1">Passenger</label>
                <h6 class="fw-bold mb-0" id="modalPassengerName">-</h6>
                <small class="text-muted" id="modalPassengerPhone">-</small>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-6">
            <div class="p-3 rounded h-100" style="background: rgba(113,221,55,0.06); border: 1px solid rgba(113,221,55,0.2);">
              <label class="text-muted small d-block mb-1"><i class="ti tabler-map-pin-up me-1 text-success"></i>Pickup</label>
              <p class="mb-0 fw-semibold small text-break" id="modalPickupAddress">-</p>
            </div>
          </div>
          <div class="col-6">
            <div class="p-3 rounded h-100" style="background: rgba(255,62,29,0.06); border: 1px solid rgba(255,62,29,0.2);">
              <label class="text-muted small d-block mb-1"><i class="ti tabler-map-pin-down me-1 text-danger"></i>Drop-off</label>
              <p class="mb-0 fw-semibold small text-break" id="modalDropoffAddress">-</p>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-4 text-center">
            <div class="p-2 rounded" style="background: rgba(0,0,0,0.03);">
              <i class="ti tabler-route text-primary d-block mb-1"></i>
              <small class="text-muted d-block">Distance</small>
              <span class="fw-bold small" id="modalDistance">-</span>
            </div>
          </div>
          <div class="col-4 text-center">
            <div class="p-2 rounded" style="background: rgba(0,0,0,0.03);">
              <i class="ti tabler-steering-wheel text-success d-block mb-1"></i>
              <small class="text-muted d-block">Driver</small>
              <span class="fw-bold small" id="modalDriver">-</span>
            </div>
          </div>
          <div class="col-4 text-center">
            <div class="p-2 rounded" style="background: rgba(0,0,0,0.03);">
              <i class="ti tabler-car text-warning d-block mb-1"></i>
              <small class="text-muted d-block">Vehicle</small>
              <span class="fw-bold small" id="modalVehicle">-</span>
            </div>
          </div>
        </div>

        <div id="modalNotesWrapper" class="mt-3 p-3 rounded" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); display:none;">
          <label class="text-muted small d-block mb-1"><i class="ti tabler-notes me-1"></i>Notes</label>
          <p class="mb-0 small" id="modalNotes">-</p>
        </div>
      </div>

      <div class="modal-footer border-top d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
        <div class="d-flex gap-2">
          <a href="#" id="modalEditBtn" class="btn btn-sm btn-outline-primary"><i class="ti tabler-edit me-1"></i> Edit</a>
          <a href="#" id="modalViewDetailsBtn" class="btn btn-sm btn-primary"><i class="ti tabler-eye me-1"></i> View Full Details</a>
        </div>
      </div>

    </div>
  </div>
</div>
{{-- ============== /TRIP DETAILS MODAL ============== --}}

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const calendarEl = document.getElementById('tripCalendar');
  const tripModal  = new bootstrap.Modal(document.getElementById('tripDetailModal'));

  // All events cache for filtering
  let allEvents = [];

  // ---- Active filters ----
  let activeFilters = new Set();
  activeFilters.add('all');

  // ---- Filter checkboxes wiring ----
  const selectAllCb = document.getElementById('filter-all');
  const filterCbs   = document.querySelectorAll('.input-filter');

  function showLoader() {
    const el = document.getElementById('calendarLoader');
    if (el) {
      el.classList.remove('d-none');
      el.classList.add('d-flex');
    }
  }

  function hideLoader() {
    const el = document.getElementById('calendarLoader');
    if (el) {
      el.classList.remove('d-flex');
      el.classList.add('d-none');
    }
  }

  selectAllCb.addEventListener('change', function() {
    showLoader();
    filterCbs.forEach(cb => {
      cb.checked = this.checked;
      if (this.checked) activeFilters.add(cb.dataset.value);
      else activeFilters.delete(cb.dataset.value);
    });
    if (this.checked) activeFilters.add('all');
    else activeFilters.delete('all');
    
    setTimeout(() => {
      calendar.refetchEvents();
    }, 50);
  });

  filterCbs.forEach(cb => {
    cb.addEventListener('change', function() {
      showLoader();
      if (this.checked) activeFilters.add(this.dataset.value);
      else activeFilters.delete(this.dataset.value);

      const allChecked = [...filterCbs].every(c => c.checked);
      selectAllCb.checked = allChecked;
      if (allChecked) activeFilters.add('all');
      else activeFilters.delete('all');

      setTimeout(() => {
        calendar.refetchEvents();
      }, 50);
    });
  });

  // ---- FullCalendar init ----
  if (calendarEl && typeof Calendar !== 'undefined') {
    window.calendar = new Calendar(calendarEl, {
      plugins: [dayGridPlugin, interactionPlugin, listPlugin, timegridPlugin],
      initialView: 'dayGridMonth',
      headerToolbar: {
        left  : 'prev,next today',
        center: 'title',
        right : 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
      },
      buttonText: {
        today   : 'Today',
        month   : 'Month',
        week    : 'Week',
        day     : 'Day',
        list    : 'List',
      },
      height: 'auto',
      editable: false,
      dayMaxEvents: 3,
      eventDisplay: 'block',
      loading: function(isLoading) {
        if (isLoading) showLoader();
        else hideLoader();
      },
      eventClassNames: function (arg) {
        const cal = arg.event.extendedProps.calendar || 'secondary';
        return ['fc-event-' + cal, 'bg-label-' + cal];
      },
      eventContent: function(arg) {
        const pName = arg.event.extendedProps.passenger || arg.event.title || 'Trip';
        let container = document.createElement('div');
        container.className = 'fc-event-main-frame d-flex align-items-center gap-1 px-1 py-1 text-truncate';
        container.innerHTML = `<span class="fw-semibold text-truncate">${pName}</span>`;
        return { domNodes: [container] };
      },
      events: function(fetchInfo, successCallback, failureCallback) {
        showLoader();
        fetch("{{ route('dispatcher.trip.events') }}")
          .then(res => res.json())
          .then(data => {
            allEvents = data;

            // If "all" active, show everything
            let finalEvents = data;
            if (!activeFilters.has('all')) {
              finalEvents = data.filter(ev => {
                const dId = String(ev.extendedProps.driver_id || 'unassigned');
                return activeFilters.has(dId);
              });
            }

            successCallback(finalEvents);
          })
          .catch(err => {
            failureCallback(err);
          })
          .finally(() => {
            hideLoader();
          });
      },

      // Event click → open detail modal
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        const props = info.event.extendedProps;
        const color = props.color || info.event.backgroundColor;

        // Header bar accent color
        document.getElementById('modalHeaderBar').style.borderBottomColor = color;

        // Avatar initial
        const initials = (props.passenger || 'T').split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
        document.getElementById('modalAvatarInitial').textContent = initials;
        document.getElementById('modalAvatarInitial').style.backgroundColor = color + '22';
        document.getElementById('modalAvatarInitial').style.color = color;

        // Title & datetime
        document.getElementById('modalTripTitle').textContent = props.passenger || info.event.title;
        document.getElementById('modalTripDateTime').textContent = info.event.start
          ? info.event.start.toLocaleString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' })
          : '-';

        // Status badge
        const statusBadge = document.getElementById('modalTripStatus');
        const status = props.status || 'scheduled';
        statusBadge.textContent = status.replace('_',' ').toUpperCase();
        const statusMap = { scheduled:'bg-warning text-dark', in_progress:'bg-primary', completed:'bg-success', cancelled:'bg-danger' };
        statusBadge.className = 'badge ' + (statusMap[status] || 'bg-secondary');

        // Trip type
        document.getElementById('modalTripType').textContent = (props.type || 'one way').replace(/\b\w/g,c=>c.toUpperCase());

        // Passenger
        document.getElementById('modalPassengerName').textContent = props.passenger || '-';
        document.getElementById('modalPassengerPhone').textContent = props.phone || '-';

        // Addresses
        document.getElementById('modalPickupAddress').textContent = props.pickup || '-';
        document.getElementById('modalDropoffAddress').textContent = props.dropoff || '-';

        // Stats
        document.getElementById('modalDistance').textContent = props.distance || 'N/A';
        document.getElementById('modalDriver').textContent   = props.driver   || 'Unassigned';
        document.getElementById('modalVehicle').textContent  = props.vehicle  || 'Unassigned';

        // Notes
        const notesEl = document.getElementById('modalNotesWrapper');
        if (props.notes && props.notes !== 'None') {
          document.getElementById('modalNotes').textContent = props.notes;
          notesEl.style.display = '';
        } else {
          notesEl.style.display = 'none';
        }

        // Dynamic Action Links
        document.getElementById('modalViewDetailsBtn').href = "{{ url('dispatcher/trip/details') }}/" + info.event.id;
        document.getElementById('modalEditBtn').href = "{{ url('dispatcher/trip/edit') }}/" + info.event.id;

        tripModal.show();
      }
    });

    calendar.render();
  }
});
</script>
@endsection
