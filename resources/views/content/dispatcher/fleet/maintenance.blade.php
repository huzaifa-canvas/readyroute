@extends('layouts/layoutMaster')

@section('title', 'Maintenance — ' . $vehicle->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php($canManage = auth()->user()->hasPermission('fleet.manage'))

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="{{ route('dispatcher.fleet.index') }}" class="btn btn-icon btn-text-secondary rounded-pill">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <h4 class="fw-bold text-heading mb-0">Maintenance</h4>
      </div>
      <p class="text-muted mb-0 small">
        {{ $vehicle->name }}
        @if($vehicle->number_plate) &middot; {{ $vehicle->number_plate }} @endif
        @if($vehicle->make_model_year) &middot; {{ $vehicle->make_model_year }} @endif
      </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('dispatcher.fleet.inspections', $vehicle->id) }}" class="btn btn-label-primary">
        <i class="ti tabler-clipboard-check me-1"></i>Inspections
      </a>
      @if($canManage)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#logModal"
                onclick="rrNewLog()">
          <i class="ti tabler-plus me-1"></i>Record work
        </button>
      @endif
    </div>
  </div>

  {{-- The four numbers an operator is actually asked for. --}}
  <div class="row g-4 mb-4">
    @php($tiles = [
      ['label' => 'Jobs recorded', 'value' => number_format($totals['jobs']), 'icon' => 'tabler-tool', 'class' => 'bg-label-primary'],
      ['label' => 'Open', 'value' => number_format($totals['open']), 'icon' => 'tabler-progress', 'class' => $totals['open'] ? 'bg-label-warning' : 'bg-label-secondary'],
      ['label' => 'Overdue', 'value' => number_format($totals['overdue']), 'icon' => 'tabler-alert-triangle', 'class' => $totals['overdue'] ? 'bg-label-danger' : 'bg-label-secondary'],
      ['label' => 'Spend to date', 'value' => '$' . number_format($totals['spend'], 2), 'icon' => 'tabler-receipt', 'class' => 'bg-label-success'],
    ])
    @foreach($tiles as $tile)
      <div class="col-6 col-xl-3">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="avatar">
              <span class="avatar-initial rounded {{ $tile['class'] }}">
                <i class="ti {{ $tile['icon'] }}"></i>
              </span>
            </span>
            <div>
              <h5 class="mb-0 fw-bold" style="font-variant-numeric: tabular-nums;">{{ $tile['value'] }}</h5>
              <small class="text-muted">{{ $tile['label'] }}</small>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if($totals['last_done'])
    <p class="text-muted small mb-4">
      <i class="ti tabler-circle-check text-success me-1"></i>
      Last completed job: <strong>{{ $totals['last_done']->format('d M Y') }}</strong>
      ({{ $totals['last_done']->diffForHumans() }}).
    </p>
  @endif

  {{-- Open work sits above history rather than inside it: a booking is
       something somebody still has to act on, and in date order among
       finished jobs it is exactly what gets missed. --}}
  @if($open->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center gap-2">
        <h5 class="mb-0">Open work</h5>
        <span class="badge bg-label-warning">{{ $open->count() }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Job</th>
              <th>Type</th>
              <th>Due</th>
              <th>Vendor</th>
              <th>Status</th>
              @if($canManage)<th class="text-end">Actions</th>@endif
            </tr>
          </thead>
          <tbody>
            @foreach($open as $log)
              <tr>
                <td>
                  <span class="fw-semibold">{{ $log->title }}</span>
                  @if($log->description)
                    <small class="text-muted d-block text-truncate" style="max-width: 320px;">{{ $log->description }}</small>
                  @endif
                </td>
                <td>{{ $log->typeLabel() }}</td>
                <td style="font-variant-numeric: tabular-nums;">
                  @if($log->due_on)
                    {{ $log->due_on->format('d M Y') }}
                    @if($log->isOverdue())
                      <small class="text-danger d-block">{{ $log->due_on->diffForHumans() }}</small>
                    @endif
                  @else
                    <span class="text-muted">&mdash;</span>
                  @endif
                </td>
                <td>{{ $log->vendor ?: '—' }}</td>
                <td><span class="badge {{ $log->statusClass() }}">{{ $log->statusLabel() }}</span></td>
                @if($canManage)
                  <td class="text-end text-nowrap">
                    @include('content.dispatcher.fleet._log-actions', ['log' => $log, 'vehicle' => $vehicle])
                  </td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">History</h5>
      <small class="text-muted">Completed and cancelled jobs, newest first.</small>
    </div>

    @if($history->isEmpty())
      <div class="card-body text-center py-5 text-muted">
        <i class="ti tabler-tool fs-1 d-block mb-2 text-secondary"></i>
        <h6 class="mb-1">Nothing recorded yet</h6>
        <p class="mb-0 small">
          @if($canManage)
            Use <strong>Record work</strong> to log a service, a repair or a booking.
          @else
            Service history will appear here once the office records it.
          @endif
        </p>
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Job</th>
              <th>Type</th>
              <th>Vendor</th>
              <th class="text-end">Odometer</th>
              <th class="text-end">Cost</th>
              <th>Status</th>
              <th>Recorded by</th>
              @if($canManage)<th class="text-end">Actions</th>@endif
            </tr>
          </thead>
          <tbody>
            @foreach($history as $log)
              <tr>
                <td class="text-nowrap" style="font-variant-numeric: tabular-nums;">
                  {{ optional($log->onDate())->format('d M Y') ?: '—' }}
                </td>
                <td>
                  <span class="fw-semibold">{{ $log->title }}</span>
                  @if($log->description)
                    <small class="text-muted d-block text-truncate" style="max-width: 280px;">{{ $log->description }}</small>
                  @endif
                  @if($log->vehicle_inspection_id)
                    <small class="text-muted d-block">
                      <i class="ti tabler-clipboard-check icon-xs me-1"></i>Raised from an inspection defect
                    </small>
                  @endif
                </td>
                <td>{{ $log->typeLabel() }}</td>
                <td>{{ $log->vendor ?: '—' }}</td>
                <td class="text-end" style="font-variant-numeric: tabular-nums;">
                  {{ $log->odometer !== null ? number_format($log->odometer) : '—' }}
                </td>
                <td class="text-end" style="font-variant-numeric: tabular-nums;">
                  {{ $log->cost !== null ? '$' . number_format((float) $log->cost, 2) : '—' }}
                </td>
                <td><span class="badge {{ $log->statusClass() }}">{{ $log->statusLabel() }}</span></td>
                <td class="text-muted small">{{ $log->creator?->name ?: '—' }}</td>
                @if($canManage)
                  <td class="text-end text-nowrap">
                    @include('content.dispatcher.fleet._log-actions', ['log' => $log, 'vehicle' => $vehicle])
                  </td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

@if($canManage)
{{-- One modal for adding and for editing: the two forms are the same fields,
     and keeping a second copy only lets them drift apart. --}}
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content" method="POST" id="logForm" action="{{ route('dispatcher.fleet.maintenance.store', $vehicle->id) }}">
      @csrf
      <input type="hidden" name="_method" id="logMethod" value="POST">

      <div class="modal-header">
        <h5 class="modal-title" id="logTitle">Record work</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label" for="log-title">What was done <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="log-title" name="title" maxlength="160" required
                   placeholder="e.g. 60,000 mile service">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="log-type">Type <span class="text-danger">*</span></label>
            <select class="form-select" id="log-type" name="type" required>
              @foreach($types as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="log-status">Status <span class="text-danger">*</span></label>
            <select class="form-select" id="log-status" name="status" required onchange="rrToggleDates()">
              @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected($value === 'completed')>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-4" id="log-performed-wrap">
            <label class="form-label" for="log-performed">Date done</label>
            <input type="date" class="form-control" id="log-performed" name="performed_on">
            <small class="text-muted">Left blank, today is used.</small>
          </div>

          <div class="col-12 col-md-4 d-none" id="log-due-wrap">
            <label class="form-label" for="log-due">Due date</label>
            <input type="date" class="form-control" id="log-due" name="due_on">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="log-vendor">Garage / vendor</label>
            <input type="text" class="form-control" id="log-vendor" name="vendor" maxlength="160">
          </div>

          <div class="col-6 col-md-4">
            <label class="form-label" for="log-cost">Cost</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" min="0" class="form-control" id="log-cost" name="cost">
            </div>
          </div>

          <div class="col-6 col-md-4">
            <label class="form-label" for="log-odometer">Odometer</label>
            <input type="number" min="0" class="form-control" id="log-odometer" name="odometer">
          </div>

          <div class="col-12">
            <label class="form-label" for="log-description">Notes</label>
            <textarea class="form-control" id="log-description" name="description" rows="3" maxlength="2000"></textarea>
          </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 mt-3 mb-0 py-2 px-3">
          <i class="ti tabler-info-circle flex-shrink-0 mt-1"></i>
          <span class="small">
            While a job is <strong>scheduled</strong> or <strong>in progress</strong>, this vehicle is kept
            off the road and shows as <strong>Maintenance</strong>. Marking the last open job complete puts
            it back to <strong>Available</strong>.
          </span>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="logSubmit">Save</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@section('page-script')
@if($canManage)
<script>
  const rrStoreUrl = @json(route('dispatcher.fleet.maintenance.store', $vehicle->id));
  const rrUpdateBase = @json(url('dispatcher/fleet/' . $vehicle->id . '/maintenance'));

  // Dates follow the status: a completed job is dated, a booking is due.
  // Showing both at once invites a record that claims to be finished and
  // scheduled at the same time.
  function rrToggleDates() {
    const done = document.getElementById('log-status').value === 'completed';
    document.getElementById('log-performed-wrap').classList.toggle('d-none', !done);
    document.getElementById('log-due-wrap').classList.toggle('d-none', done);
  }

  function rrNewLog() {
    const form = document.getElementById('logForm');
    form.reset();
    form.action = rrStoreUrl;
    document.getElementById('logMethod').value = 'POST';
    document.getElementById('logTitle').textContent = 'Record work';
    document.getElementById('logSubmit').textContent = 'Save';
    rrToggleDates();
  }

  function rrEditLog(button) {
    const data = button.dataset;
    const form = document.getElementById('logForm');

    form.action = rrUpdateBase + '/' + data.id;
    document.getElementById('logMethod').value = 'PUT';
    document.getElementById('logTitle').textContent = 'Edit record';
    document.getElementById('logSubmit').textContent = 'Save changes';

    document.getElementById('log-title').value = data.title || '';
    document.getElementById('log-type').value = data.type || 'service';
    document.getElementById('log-status').value = data.status || 'completed';
    document.getElementById('log-performed').value = data.performed || '';
    document.getElementById('log-due').value = data.due || '';
    document.getElementById('log-vendor').value = data.vendor || '';
    document.getElementById('log-cost').value = data.cost || '';
    document.getElementById('log-odometer').value = data.odometer || '';
    document.getElementById('log-description').value = data.description || '';

    rrToggleDates();
  }

  document.addEventListener('DOMContentLoaded', rrToggleDates);
</script>
@endif
@endsection
