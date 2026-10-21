@extends('layouts/layoutMaster')

@section('title', 'Inspections — ' . $vehicle->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="{{ route('dispatcher.fleet.index') }}" class="btn btn-icon btn-text-secondary rounded-pill">
          <i class="ti tabler-arrow-left"></i>
        </a>
        <h4 class="fw-bold text-heading mb-0">Pre-trip inspections</h4>
      </div>
      <p class="text-muted mb-0 small">
        {{ $vehicle->name }}
        @if($vehicle->number_plate) &middot; {{ $vehicle->number_plate }} @endif
      </p>
    </div>

    <a href="{{ route('dispatcher.fleet.maintenance', $vehicle->id) }}" class="btn btn-label-primary">
      <i class="ti tabler-tool me-1"></i>Maintenance
    </a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar"><span class="avatar-initial rounded bg-label-primary"><i class="ti tabler-clipboard-check"></i></span></span>
          <div>
            <h5 class="mb-0 fw-bold" style="font-variant-numeric: tabular-nums;">{{ number_format($totals['total']) }}</h5>
            <small class="text-muted">Checks filed</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar">
            <span class="avatar-initial rounded {{ $totals['defects'] ? 'bg-label-danger' : 'bg-label-secondary' }}">
              <i class="ti tabler-alert-triangle"></i>
            </span>
          </span>
          <div>
            <h5 class="mb-0 fw-bold" style="font-variant-numeric: tabular-nums;">{{ number_format($totals['defects']) }}</h5>
            <small class="text-muted">With defects reported</small>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar"><span class="avatar-initial rounded bg-label-info"><i class="ti tabler-calendar"></i></span></span>
          <div>
            <h5 class="mb-0 fw-bold">
              {{ $totals['last'] ? \Illuminate\Support\Carbon::parse($totals['last'])->format('d M Y') : '—' }}
            </h5>
            <small class="text-muted">Last checked</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Read-only on purpose: the record belongs to the driver who signed it,
       and a check the office can edit afterwards is worth nothing in an
       audit. --}}
  <div class="alert alert-info d-flex align-items-start gap-2" role="note">
    <i class="ti tabler-info-circle flex-shrink-0 mt-1"></i>
    <span class="small">
      Drivers file one check per day, not one per trip. These are their own signed records and
      cannot be edited here — if something needs acting on, record it under
      <a href="{{ route('dispatcher.fleet.maintenance', $vehicle->id) }}" class="alert-link">Maintenance</a>.
    </span>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">All checks</h5>
      <small class="text-muted">Newest first.</small>
    </div>

    @if($inspections->isEmpty())
      <div class="card-body text-center py-5 text-muted">
        <i class="ti tabler-clipboard-off fs-1 d-block mb-2 text-secondary"></i>
        <h6 class="mb-1">No checks filed for this vehicle</h6>
        <p class="mb-0 small">
          One appears here each day the assigned driver completes their pre-trip inspection.
        </p>
      </div>
    @else
      <div class="accordion accordion-flush" id="inspectionList">
        @foreach($inspections as $inspection)
          @php($checklist = $inspection->checklist($items))
          @php($tally = \App\Models\VehicleInspection::checklistTally($checklist))
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse" data-bs-target="#insp-{{ $inspection->id }}">
                <div class="d-flex flex-wrap align-items-center gap-3 w-100 pe-3">
                  <span class="fw-semibold" style="font-variant-numeric: tabular-nums; min-width: 6.5rem;">
                    {{ optional($inspection->inspection_date)->format('d M Y') }}
                  </span>

                  <span class="text-muted small flex-grow-1">
                    <i class="ti tabler-steering-wheel icon-xs me-1"></i>{{ $inspection->driver?->name ?: 'Unknown driver' }}
                  </span>

                  {{-- Counted from the checklist itself, so the badge and the
                       rows below it can never disagree. --}}
                  @if($tally['defects'] > 0)
                    <span class="badge bg-label-danger">
                      {{ $tally['defects'] }} {{ \Illuminate\Support\Str::plural('defect', $tally['defects']) }}
                    </span>
                  @elseif($tally['passed'] > 0)
                    <span class="badge bg-label-success">No defects</span>
                  @endif

                  {{-- A check signed off with questions left blank is the one
                       worth seeing from the list, not only on opening it. --}}
                  @if($tally['not_answered'] > 0)
                    <span class="badge bg-label-warning">
                      {{ $tally['not_answered'] }} unanswered
                    </span>
                  @endif

                  <span class="badge {{ $inspection->isSubmitted() ? 'bg-label-primary' : 'bg-label-secondary' }}">
                    {{ $inspection->isSubmitted() ? 'Signed off' : 'Draft' }}
                  </span>
                </div>
              </button>
            </h2>

            <div id="insp-{{ $inspection->id }}" class="accordion-collapse collapse" data-bs-parent="#inspectionList">
              <div class="accordion-body">
                @if($checklist->isEmpty())
                  <p class="text-muted small mb-0">
                    This company has no checklist set up, so there was nothing for the driver to answer.
                  </p>
                @else
                  {{-- The summary first: on a long checklist the counts are
                       what someone reads, and the rows are what they check. --}}
                  <div class="d-flex flex-wrap gap-3 mb-3 small">
                    <span class="text-success">
                      <i class="ti tabler-circle-check icon-xs me-1"></i>{{ $tally['passed'] }} passed
                    </span>
                    <span class="{{ $tally['defects'] ? 'text-danger fw-semibold' : 'text-muted' }}">
                      <i class="ti tabler-alert-triangle icon-xs me-1"></i>{{ $tally['defects'] }} with defects
                    </span>
                    <span class="{{ $tally['not_answered'] ? 'text-warning fw-semibold' : 'text-muted' }}">
                      <i class="ti tabler-circle-dashed icon-xs me-1"></i>{{ $tally['not_answered'] }} not answered
                    </span>
                    <span class="text-muted">of {{ $tally['total'] }} checkpoints</span>
                  </div>

                  @if($tally['not_answered'] === $tally['total'])
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3">
                      <i class="ti tabler-alert-triangle flex-shrink-0 mt-1"></i>
                      <span class="small">
                        This check was
                        {{ $inspection->isSubmitted() ? 'signed off' : 'started' }}
                        without a single checkpoint being answered.
                      </span>
                    </div>
                  @endif

                  <div class="row g-2">
                    @foreach($checklist as $row)
                      <div class="col-12 col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded border
                                    {{ $row['is_defect'] ? 'border-danger' : ($row['is_answered'] ? 'border-transparent' : 'border-warning') }}">
                          @if($row['is_defect'])
                            <i class="ti tabler-alert-triangle text-danger flex-shrink-0 mt-1"></i>
                          @elseif($row['is_pass'])
                            <i class="ti tabler-circle-check text-success flex-shrink-0 mt-1"></i>
                          @else
                            <i class="ti tabler-circle-dashed text-warning flex-shrink-0 mt-1"></i>
                          @endif

                          <div class="small flex-grow-1">
                            <span class="{{ $row['is_answered'] ? '' : 'text-muted' }} {{ $row['is_defect'] ? 'fw-semibold' : '' }}">
                              {{ $row['label'] }}
                            </span>

                            @if($row['required'] && ! $row['is_answered'])
                              <span class="badge bg-label-danger ms-1">Required</span>
                            @endif
                            @if($row['retired'])
                              <span class="badge bg-label-secondary ms-1" title="No longer on the checklist">Retired</span>
                            @endif

                            @if($row['note'])
                              <span class="text-muted d-block">{{ $row['note'] }}</span>
                            @endif
                          </div>

                          <span class="badge {{ $row['is_defect'] ? 'bg-label-danger' : ($row['is_pass'] ? 'bg-label-success' : 'bg-label-warning') }} flex-shrink-0">
                            {{ $row['status_label'] }}
                          </span>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif

                <div class="d-flex flex-wrap gap-4 mt-3 pt-3 border-top text-muted small">
                  <span>
                    <i class="ti tabler-clock icon-xs me-1"></i>
                    {{ $inspection->submitted_at ? 'Signed ' . $inspection->submitted_at->format('d M Y, g:i A') : 'Not signed off' }}
                  </span>
                  @if($inspection->driver?->driver_code)
                    <span><i class="ti tabler-id icon-xs me-1"></i>{{ $inspection->driver->driver_code }}</span>
                  @endif
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if($inspections->hasPages())
        <div class="card-footer">
          {{ $inspections->links() }}
        </div>
      @endif
    @endif
  </div>
</div>
@endsection
