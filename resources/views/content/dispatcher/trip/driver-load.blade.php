@extends('layouts/layoutMaster')

@section('title', 'Driver Trips')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1">Driver Trips</h4>
      <p class="text-muted mb-0">
        How many trips each driver is carrying
        @if($span === 1)
          on <strong>{{ $from->isToday() ? 'today' : $from->format('D, d M Y') }}</strong>.
        @else
          from <strong>{{ $from->format('d M Y') }}</strong>
          to <strong>{{ $to->format('d M Y') }}</strong>
          <span class="text-muted">({{ $span }} days)</span>.
        @endif
      </p>
    </div>

    {{-- One day at a time. The arrows are there because stepping through
         consecutive days is what this page is actually used for; typing a
         date is the exception, not the rule. --}}
    {{-- One control for both cases: picking a single day leaves the range
         ends equal, picking two sets a span. The arrows then move by whatever
         that span is, so paging never overlaps or skips days. --}}
    <form method="GET" action="{{ route('dispatcher.trip.driver-load') }}" id="rangeForm"
          class="d-flex align-items-center gap-2">
      <a href="{{ route('dispatcher.trip.driver-load', [
            'date_from' => $from->copy()->subDays($span)->toDateString(),
            'date_to'   => $to->copy()->subDays($span)->toDateString(),
         ]) }}" class="btn btn-icon btn-label-secondary" aria-label="Previous period">
        <i class="ti tabler-chevron-left"></i>
      </a>

      <div class="input-group" style="min-width: 15rem;">
        <span class="input-group-text"><i class="ti tabler-calendar"></i></span>
        <input type="text" id="load_range" class="form-control" autocomplete="off"
               placeholder="Pick a day or a range" />
      </div>

      {{-- What actually gets submitted; the visible field is the picker's. --}}
      <input type="hidden" name="date_from" id="date_from" value="{{ $from->toDateString() }}" />
      <input type="hidden" name="date_to" id="date_to" value="{{ $to->toDateString() }}" />

      <a href="{{ route('dispatcher.trip.driver-load', [
            'date_from' => $from->copy()->addDays($span)->toDateString(),
            'date_to'   => $to->copy()->addDays($span)->toDateString(),
         ]) }}" class="btn btn-icon btn-label-secondary" aria-label="Next period">
        <i class="ti tabler-chevron-right"></i>
      </a>

      @unless($span === 1 && $from->isToday())
        <a href="{{ route('dispatcher.trip.driver-load') }}" class="btn btn-label-primary">Today</a>
      @endunless
    </form>
  </div>

  {{-- Totals, with unassigned called out: a day can look quiet per driver
       while trips are still sitting with nobody on them. --}}
  <div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body text-center p-3">
          <h4 class="fw-bold mb-0">{{ $assigned + $unassigned }}</h4>
          <small class="text-muted">{{ $span === 1 ? 'Trips this day' : 'Trips in range' }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body text-center p-3">
          <h4 class="fw-bold mb-0">{{ $assigned }}</h4>
          <small class="text-muted">Assigned</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100 {{ $unassigned > 0 ? 'border border-warning' : '' }}">
        <div class="card-body text-center p-3">
          <h4 class="fw-bold mb-0 {{ $unassigned > 0 ? 'text-warning' : '' }}">{{ $unassigned }}</h4>
          <small class="text-muted">Unassigned</small>
          @if($unassigned > 0)
            <a class="d-block small"
               href="{{ route('dispatcher.trip.list', ['driver_id' => 'unassigned', 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}">
              View list
            </a>
          @endif
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body text-center p-3">
          <h4 class="fw-bold mb-0">{{ $span === 1 ? $drivers->filter(fn ($d) => $d->isAvailableOn($from))->count() : $drivers->count() }}</h4>
          <small class="text-muted">{{ $span === 1 ? 'Drivers working' : 'Drivers' }}</small>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 class="mb-0">Drivers</h5>
      <span class="badge bg-label-secondary">{{ $drivers->count() }} total</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Driver</th>
            <th>Vehicle</th>
            <th>Available</th>
            <th class="text-center">Trips</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($drivers as $driver)
            @php($count = $counts[$driver->id] ?? 0)
            @php($worksToday = $span === 1 ? $driver->isAvailableOn($from) : true)
            <tr>
              <td>
                <div class="d-flex align-items-center">
                  <div class="avatar avatar-sm me-3">
                    <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
                  </div>
                  <div>
                    <span class="fw-medium text-heading d-block">{{ $driver->name }}</span>
                    <small class="text-muted">{{ $driver->driver_code }}</small>
                  </div>
                </div>
              </td>

              <td>
                @if($driver->assignedVehicle)
                  <span class="text-heading">{{ $driver->assignedVehicle->name }}</span>
                  @if($driver->assignedVehicle->number_plate)
                    <small class="d-block text-muted">{{ $driver->assignedVehicle->number_plate }}</small>
                  @endif
                @else
                  <span class="badge bg-label-warning">Not assigned</span>
                @endif
              </td>

              <td>
                @if($span > 1)
                  {{-- Over a span, "working today" means nothing, so the days
                       themselves are what is useful. --}}
                  <span class="badge bg-label-secondary">{{ $driver->availabilityShort() }}</span>
                @elseif($worksToday)
                  <span class="badge bg-label-success">Working</span>
                @else
                  {{-- Off today, so any trips showing against them were booked
                       before the rule or before their days changed. --}}
                  <span class="badge bg-label-secondary">Off &mdash; only {{ $driver->availabilityShort() }}</span>
                @endif
              </td>

              <td class="text-center">
                <span class="badge rounded-pill {{ $count > 0 ? 'bg-primary' : 'bg-label-secondary' }} fs-6 px-3">
                  {{ $count }}
                </span>
              </td>

              <td class="text-end">
                @if($count > 0)
                  {{-- Carries the same day and driver through, so the list
                       shows exactly the trips this count was made from. --}}
                  <a class="btn btn-sm btn-primary"
                     href="{{ route('dispatcher.trip.list', [
                        'driver_id' => $driver->id,
                        'date_from' => $from->toDateString(),
                        'date_to'   => $to->toDateString(),
                     ]) }}">
                    <i class="ti tabler-list-search me-1"></i> View list
                  </a>
                @else
                  <span class="text-muted small">No trips</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center py-5 text-muted">
                <i class="ti tabler-steering-wheel icon-48px d-block mb-2"></i>
                No drivers yet. Add one from Driver Management.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const field = document.getElementById('load_range');
    const from  = document.getElementById('date_from');
    const to    = document.getElementById('date_to');
    const form  = document.getElementById('rangeForm');

    if (!field || !from || !to || !form) return;

    function submitWith(start, end) {
      from.value = start;
      to.value = end;
      form.submit();
    }

    if (typeof flatpickr === 'undefined') {
      // Picker did not load. Fall back to two plain date fields so the page
      // can still be filtered.
      from.type = 'date';
      to.type = 'date';
      field.closest('.input-group').replaceWith(from, to);
      [from, to].forEach(el => el.classList.add('form-control'));
      [from, to].forEach(el => el.addEventListener('change', () => form.submit()));
      return;
    }

    flatpickr(field, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      defaultDate: [from.value, to.value],

      /*
       * onClose rather than onChange: in range mode the first click fires a
       * change with only one date, and submitting then would throw away the
       * second half of the range the user was in the middle of picking.
       */
      onClose: function (selected, _str, instance) {
        if (selected.length === 0) return;

        const iso = (d) => instance.formatDate(d, 'Y-m-d');

        // One date picked means a single day: both ends are the same.
        const start = iso(selected[0]);
        const end = iso(selected[selected.length - 1]);

        if (start === from.value && end === to.value) return;   // nothing moved

        submitWith(start, end);
      },
    });
  });
</script>
@endsection
