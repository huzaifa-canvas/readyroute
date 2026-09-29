@extends('layouts/layoutMaster')

@section('title', $incident->headline())

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('dispatcher.incidents.index') }}">Incidents</a></li>
      <li class="breadcrumb-item active" aria-current="page">#{{ $incident->id }}</li>
    </ol>
  </nav>

  @if($incident->isSos() && ! $incident->isResolved())
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
      <i class="ti tabler-urgent fs-4 flex-shrink-0"></i>
      <div>
        <strong>Emergency alert.</strong>
        Raised {{ $incident->created_at->diffForHumans() }} by {{ $incident->driver?->name }}.
      </div>
    </div>
  @endif

  <div class="row g-4">

    {{-- Detail --}}
    <div class="col-12 col-lg-8">
      <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="min-w-0">
            <h5 class="mb-1 text-break">{{ $incident->headline() }}</h5>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge {{ $incident->severityClass() }}">{{ $incident->severityLabel() }}</span>
              <span class="badge {{ $incident->statusClass() }}">{{ $incident->statusLabel() }}</span>
              <span class="badge bg-label-secondary">{{ $incident->typeLabel() }}</span>
            </div>
          </div>
          <small class="text-muted flex-shrink-0">{{ $incident->created_at->format('d M Y, g:i A') }}</small>
        </div>

        <div class="card-body">
          @if($incident->description)
            <p class="mb-4 text-break">{{ $incident->description }}</p>
          @else
            <p class="mb-4 text-muted fst-italic">
              No description was given.
              @if($incident->isSos())
                An SOS is sent with one tap, so the driver may add detail later.
              @endif
            </p>
          @endif

          <dl class="row mb-0">
            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Driver</dt>
            <dd class="col-7 col-sm-8 py-1">
              {{ $incident->driver?->name ?? '—' }}
              @if($incident->driver?->phone_number)
                <a href="tel:{{ $incident->driver->phone_number }}" class="ms-2 small">
                  <i class="ti tabler-phone icon-xs me-1"></i>{{ $incident->driver->phone_number }}
                </a>
              @endif
            </dd>

            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Trip</dt>
            <dd class="col-7 col-sm-8 py-1">
              @if($incident->trip)
                <a href="{{ route('dispatcher.trip.details', $incident->trip->id) }}">
                  {{ $incident->trip->reference() }}
                </a>
                <span class="text-muted">&middot; {{ $incident->trip->passengerName() }}</span>
              @else
                <span class="text-muted">Not linked to a trip</span>
              @endif
            </dd>

            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Vehicle</dt>
            <dd class="col-7 col-sm-8 py-1">{{ $incident->vehicle?->name ?? '—' }}</dd>

            <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Location</dt>
            <dd class="col-7 col-sm-8 py-1">
              @if($incident->lat && $incident->lng)
                {{ $incident->address ?: number_format($incident->lat, 5) . ', ' . number_format($incident->lng, 5) }}
                <a class="d-block small"
                   href="https://www.google.com/maps/search/?api=1&query={{ $incident->lat }},{{ $incident->lng }}"
                   target="_blank" rel="noopener">
                  <i class="ti tabler-map-pin icon-xs me-1"></i>Open in Maps
                </a>
              @else
                <span class="text-muted">No position recorded</span>
              @endif
            </dd>

            @if($incident->acknowledged_at)
              <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Acknowledged</dt>
              <dd class="col-7 col-sm-8 py-1">
                {{ $incident->acknowledged_at->format('d M Y, g:i A') }}
                <span class="text-muted">by {{ $incident->acknowledgedBy?->name ?? 'someone' }}</span>
              </dd>
            @endif

            @if($incident->resolved_at)
              <dt class="col-5 col-sm-4 text-muted fw-normal small py-1">Resolved</dt>
              <dd class="col-7 col-sm-8 py-1">{{ $incident->resolved_at->format('d M Y, g:i A') }}</dd>
            @endif
          </dl>

          @if($incident->resolution_note)
            <hr />
            <h6 class="mb-2">Resolution</h6>
            <p class="mb-0 text-muted text-break">{{ $incident->resolution_note }}</p>
          @endif
        </div>
      </div>

      {{-- Photos --}}
      @if($photos->isNotEmpty())
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Photos ({{ $photos->count() }})</h6>
          <small class="text-muted">Links expire shortly after this page was loaded.</small>
        </div>
        <div class="card-body">
          <div class="row g-3">
            @foreach($photos as $photo)
              <div class="col-6 col-md-4">
                <a href="{{ $photo }}" target="_blank" rel="noopener">
                  <img src="{{ $photo }}" alt="Incident photo {{ $loop->iteration }}"
                       class="img-fluid rounded border" />
                </a>
              </div>
            @endforeach
          </div>
        </div>
      </div>
      @endif
    </div>

    {{-- Actions --}}
    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0">Actions</h6>
        </div>
        <div class="card-body d-flex flex-column gap-3">

          @if($incident->status === 'open')
            <form method="POST" action="{{ route('dispatcher.incidents.acknowledge', $incident->id) }}">
              @csrf
              <button type="submit" class="btn btn-info w-100">
                <i class="ti tabler-eye-check me-1"></i>Acknowledge
              </button>
              <small class="text-muted d-block mt-1">
                Tells the driver someone is dealing with it.
              </small>
            </form>
          @endif

          @unless($incident->isResolved())
            <form method="POST" action="{{ route('dispatcher.incidents.severity', $incident->id) }}">
              @csrf
              <label class="form-label" for="severity">Severity</label>
              <div class="d-flex gap-2">
                <select id="severity" name="severity" class="form-select">
                  @foreach(\App\Models\TripIncident::SEVERITIES as $key => $label)
                    <option value="{{ $key }}" @selected($incident->severity === $key)>{{ $label }}</option>
                  @endforeach
                </select>
                <button type="submit" class="btn btn-label-secondary flex-shrink-0">Set</button>
              </div>
            </form>

            <hr class="my-0" />

            <form method="POST" action="{{ route('dispatcher.incidents.resolve', $incident->id) }}">
              @csrf
              <label class="form-label" for="resolution_note">Resolution note</label>
              <textarea id="resolution_note" name="resolution_note" rows="3" maxlength="2000"
                        class="form-control mb-2"
                        placeholder="What was done about it?"></textarea>
              <button type="submit" class="btn btn-success w-100">
                <i class="ti tabler-circle-check me-1"></i>Mark Resolved
              </button>
            </form>
          @else
            <div class="text-center py-3">
              <i class="ti tabler-circle-check fs-1 text-success d-block mb-2"></i>
              <p class="mb-0 text-muted small">
                Resolved {{ $incident->resolved_at?->diffForHumans() }}.
              </p>
            </div>
          @endunless
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
