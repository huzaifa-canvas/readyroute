@extends('layouts/layoutMaster')

@section('title', 'My Drivers - Dispatcher')

@section('content')

{{-- Success/Error Messages --}}
@if(session('success'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<h4 class="mb-1">Drivers</h4>
<p class="mb-6">Manage your assigned drivers and credentials.</p>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-4">
    <h5 class="mb-0">Driver List</h5>
    <div class="d-flex gap-3 align-items-center">
      <a href="{{ route('dispatcher.driver.create') }}" class="btn btn-primary">
        <i class="icon-base ti tabler-plus me-2"></i> Add New Driver
      </a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Driver</th>
          <th>Vehicle</th>
          <th>Available</th>
          <th>Phone</th>
          <th>License #</th>
          <th class="text-center">Trips</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($drivers as $index => $driver)
        <tr>
          <td>{{ $drivers->firstItem() + $index }}</td>
          <td>
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm me-3">
                <img src="{{ $driver->avatar_url }}" alt="Avatar" class="rounded-circle" />
              </div>
              <div>
                <span class="fw-medium d-block {{ $driver->isSuspended() ? 'text-muted' : '' }}">
                  {{ $driver->name }}
                </span>
                <small class="text-muted">{{ $driver->email }}</small>
              </div>
            </div>
          </td>
          <td>
            @if($driver->assignedVehicle)
              <span class="fw-medium text-heading">{{ $driver->assignedVehicle->name }}</span>
              @if($driver->assignedVehicle->number_plate)
                <small class="d-block text-muted">{{ $driver->assignedVehicle->number_plate }}</small>
              @endif
            @else
              {{-- A driver with no vehicle cannot run a trip or open a
                   pre-trip inspection, so it is flagged rather than left blank. --}}
              <span class="badge bg-label-warning">Not assigned</span>
            @endif
          </td>
          <td>
            @php($availability = $driver->availability())
            @if($availability['mon_fri'] || $availability['sat'] || $availability['sun'])
              <div class="d-flex flex-wrap gap-1">
                @if($availability['mon_fri'])<span class="badge bg-label-success">Mon&ndash;Fri</span>@endif
                @if($availability['sat'])<span class="badge bg-label-success">Sat</span>@endif
                @if($availability['sun'])<span class="badge bg-label-success">Sun</span>@endif
                @if($availability['on_call'])<span class="badge bg-label-info">On-call</span>@endif
              </div>
            @elseif($availability['on_call'])
              {{-- On-call with no working days: reachable for emergencies, but
                   not on the roster for any day, so trips cannot be given to
                   them from the trip form. --}}
              <span class="badge bg-label-info">On-call only</span>
            @else
              <span class="badge bg-label-warning">No days set</span>
            @endif
          </td>
          <td>{{ $driver->phone_number ?? '-' }}</td>
          <td>{{ $driver->getMeta('driver_license_number', '-') }}</td>
          <td class="text-center">
            {{-- Work currently on their plate, which is also what decides
                 whether they can be deactivated. Linked, because the next
                 thing anyone wants after seeing a number is the list behind
                 it. --}}
            @if($driver->active_trips_count > 0)
              <a href="{{ route('dispatcher.trip.list', ['driver_id' => $driver->id]) }}"
                 class="badge bg-label-info text-decoration-none"
                 title="{{ $driver->active_trips_count }} {{ \Illuminate\Support\Str::plural('trip', $driver->active_trips_count) }} assigned. Open the list.">
                {{ $driver->active_trips_count }}
              </a>
            @else
              <span class="text-muted">&mdash;</span>
            @endif
          </td>
          <td>
            @if($driver->isSuspended())
              <span class="badge bg-danger">
                <i class="icon-base ti tabler-user-off icon-xs me-1"></i>Deactivated
              </span>
              @if($driver->suspension_reason)
                <small class="text-muted d-block mt-1" style="max-width: 180px;">{{ $driver->suspension_reason }}</small>
              @endif
            @else
              <span class="badge bg-label-success">
                <i class="icon-base ti tabler-user-check icon-xs me-1"></i>Active
              </span>
            @endif
          </td>
          <td class="text-end">
            <div class="d-flex gap-1 justify-content-end align-items-center">
              <a href="{{ route('dispatcher.driver.edit', $driver->id) }}"
                 class="btn btn-icon btn-sm btn-label-primary" title="Edit driver">
                <i class="icon-base ti tabler-edit"></i>
              </a>

              {{-- Always in the same place, and never hidden or greyed when the
                   driver still has work: a control that silently does nothing
                   teaches nobody why. It opens and says what has to happen
                   first instead. Which state the row is in is read from the
                   Status column, so the button only needs its tooltip. --}}
              @if($driver->isSuspended())
                <form action="{{ route('dispatcher.driver.activate', $driver->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-icon btn-sm btn-label-success"
                          title="Reactivate {{ $driver->name }}"
                          onclick="return confirm('Reactivate {{ $driver->name }}? They will be able to sign in to the driver app again.')">
                    <i class="icon-base ti tabler-user-check"></i>
                  </button>
                </form>
              @elseif($driver->active_trips_count > 0)
                <button type="button" class="btn btn-icon btn-sm btn-label-warning"
                        title="Deactivate {{ $driver->name }}"
                        data-bs-toggle="modal" data-bs-target="#blockedModal"
                        onclick="rrBlocked(this)"
                        data-name="{{ $driver->name }}"
                        data-count="{{ $driver->active_trips_count }}"
                        data-trips="{{ route('dispatcher.trip.list', ['driver_id' => $driver->id]) }}">
                  <i class="icon-base ti tabler-user-off"></i>
                </button>
              @else
                <button type="button" class="btn btn-icon btn-sm btn-label-warning"
                        title="Deactivate {{ $driver->name }}"
                        data-bs-toggle="modal" data-bs-target="#deactivateModal"
                        onclick="rrDeactivate(this)"
                        data-id="{{ $driver->id }}"
                        data-name="{{ $driver->name }}">
                  <i class="icon-base ti tabler-user-off"></i>
                </button>
              @endif

              <form action="{{ route('dispatcher.driver.delete', $driver->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-icon btn-sm btn-label-danger"
                        title="Delete driver"
                        onclick="return confirm('Are you sure you want to delete this driver?')">
                  <i class="icon-base ti tabler-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="text-center py-4 text-muted">No drivers found. Click "Add New Driver" to create one.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($drivers->hasPages())
  <div class="card-footer d-flex justify-content-center">
    {{ $drivers->links() }}
  </div>
  @endif
</div>


{{-- Why the driver cannot be switched off, and the way to fix it.
     This was a tooltip on an icon that looked almost the same as the one next
     to it, so the reason was effectively invisible and clicking jumped
     somewhere unexplained. --}}
<div class="modal fade" id="blockedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="icon-base ti tabler-alert-triangle text-warning me-1"></i>
          Reassign <span id="blockedName"></span>'s trips first
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="mb-3">
          <strong id="blockedName2"></strong> still has
          <strong><span id="blockedCount"></span></strong> assigned.
        </p>
        <p class="text-muted small mb-0">
          A driver who cannot sign in but is still named on a run leaves that run with nobody
          driving it, and nothing on the board saying so. Move those trips to another driver, or
          cancel them, and the driver can then be deactivated.
        </p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
        <a href="#" class="btn btn-primary" id="blockedTripsLink">
          <i class="icon-base ti tabler-route icon-xs me-1"></i>Open their trips
        </a>
      </div>
    </div>
  </div>
</div>

{{-- Deactivating asks for a reason, because the driver is shown it when they
     next try to sign in, and "contact your dispatcher" with no explanation is
     what makes someone keep retrying. --}}
<div class="modal fade" id="deactivateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="deactivateForm">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Deactivate <span id="deactivateName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="text-muted small">
          They will not be able to sign in to the driver app, and any session they have open now
          will end. Their record and trip history are kept, and you can reactivate them at any time.
        </p>

        <label class="form-label" for="deactivate-reason">Reason <span class="text-muted">(optional)</span></label>
        <input type="text" class="form-control" id="deactivate-reason" name="suspension_reason"
               maxlength="255" placeholder="e.g. Licence expired, on leave until March">
        <small class="text-muted">Shown to the driver when they try to sign in.</small>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning">Deactivate</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('page-script')
<script>
  const rrDeactivateBase = @json(url('dispatcher/driver'));

  function rrDeactivate(button) {
    document.getElementById('deactivateName').textContent = button.dataset.name;
    document.getElementById('deactivate-reason').value = '';
    document.getElementById('deactivateForm').action =
      rrDeactivateBase + '/' + button.dataset.id + '/deactivate';
  }

  function rrBlocked(button) {
    const count = Number(button.dataset.count);

    document.getElementById('blockedName').textContent = button.dataset.name;
    document.getElementById('blockedName2').textContent = button.dataset.name;
    document.getElementById('blockedCount').textContent =
      count + (count === 1 ? ' trip' : ' trips');
    document.getElementById('blockedTripsLink').href = button.dataset.trips;
  }
</script>
@endsection
