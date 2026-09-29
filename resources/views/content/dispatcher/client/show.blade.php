@extends('layouts/layoutMaster')

@section('title', $client->full_name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('dispatcher.client.index') }}">Client Profiles</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $client->full_name }}</li>
    </ol>
  </nav>

  {{-- Header --}}
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center gap-4">
        <div class="avatar avatar-xl flex-shrink-0 mx-auto mx-md-0">
          <span class="avatar-initial rounded-circle bg-label-primary fs-4">
            {{ strtoupper(substr($client->full_name, 0, 1)) }}
          </span>
        </div>

        <div class="flex-grow-1 text-center text-md-start min-w-0">
          <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
            <h4 class="fw-bold text-heading mb-0 text-break">{{ $client->full_name }}</h4>
            @if($client->funding_type)
              <span class="badge bg-label-primary text-capitalize">{{ $client->funding_type }}</span>
            @endif
          </div>
          <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3 text-muted small">
            @if($client->phone_number)
              <a href="tel:{{ $client->phone_number }}" class="text-muted text-decoration-none">
                <i class="ti tabler-phone icon-xs me-1"></i>{{ $client->phone_number }}
              </a>
            @endif
            @if($client->email)
              <span class="text-break"><i class="ti tabler-mail icon-xs me-1"></i>{{ $client->email }}</span>
            @endif
            @if($client->age)
              <span><i class="ti tabler-cake icon-xs me-1"></i>{{ $client->age }} yrs</span>
            @endif
            <span><i class="ti tabler-route icon-xs me-1"></i>{{ $tripCount }} {{ $tripCount === 1 ? 'trip' : 'trips' }}</span>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-end">
          <a href="{{ route('dispatcher.client.edit', $client->id) }}" class="btn btn-primary">
            <i class="ti tabler-edit me-1"></i>Edit
          </a>
          <a href="{{ route('dispatcher.trip.create') }}?client_id={{ $client->id }}" class="btn btn-label-primary">
            <i class="ti tabler-plus me-1"></i>New Trip
          </a>
        </div>
      </div>

      {{-- Transport requirements --}}
      @php
        $requirements = array_filter([
          $client->wheelchair_required   ? ['Wheelchair', 'tabler-wheelchair'] : null,
          $client->ambulatory_assistance ? ['Ambulatory assistance', 'tabler-walk'] : null,
          $client->stretcher_transport   ? ['Stretcher', 'tabler-bed'] : null,
          $client->bariatric_vehicle     ? ['Bariatric vehicle', 'tabler-ambulance'] : null,
        ]);
      @endphp
      @if(count($requirements) > 0)
        <hr class="my-4" />
        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
          @foreach($requirements as $requirement)
            <span class="badge bg-label-warning">
              <i class="ti {{ $requirement[1] }} icon-xs me-1"></i>{{ $requirement[0] }}
            </span>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  <div class="row g-4">

    {{-- Details --}}
    <div class="col-12 col-lg-5">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Profile Details</h5>
        </div>
        <div class="card-body">
          @php
            $details = [
              ['Date of birth',      $client->dob?->format('d M Y')],
              ['Home address',       trim(collect([$client->home_address, $client->apt_unit])->filter()->implode(', ')) ?: null],
              ['City / ZIP',         trim(collect([$client->city, $client->zip_code])->filter()->implode(', ')) ?: null],
              ['Funding source',     $client->funding_type ? ucfirst($client->funding_type) : null],
              ['Insurance ID',       $client->insurance_id],
              ['Emergency contact',  $client->emergency_contact_name],
              ['Emergency phone',    $client->emergency_contact_phone],
            ];
          @endphp

          <dl class="row mb-0">
            @foreach($details as [$label, $value])
              <dt class="col-5 col-sm-5 text-muted fw-normal small py-1">{{ $label }}</dt>
              <dd class="col-7 col-sm-7 py-1 text-break">{{ $value ?: '—' }}</dd>
            @endforeach
          </dl>

          @if($client->special_notes)
            <hr />
            <h6 class="mb-2">Standing Notes</h6>
            <p class="text-muted mb-0 small">{{ $client->special_notes }}</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Notes thread --}}
    <div class="col-12 col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-0">Dispatcher Notes</h5>
            <small class="text-muted">Dated notes. Drivers see the ones marked visible.</small>
          </div>
          <span class="badge bg-label-secondary">{{ $client->notes->count() }}</span>
        </div>

        <div class="card-body">
          {{-- Add note --}}
          <form method="POST" action="{{ route('dispatcher.client.note.store', $client->id) }}" class="mb-4">
            @csrf
            <label class="form-label" for="body">Add a note</label>
            <textarea id="body" name="body" rows="3" maxlength="2000"
                      class="form-control @error('body') is-invalid @enderror"
                      placeholder="e.g. Client prefers the front seat; ring the doorbell twice." required></textarea>
            @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="visible_to_driver"
                       name="visible_to_driver" value="1" checked />
                <label class="form-check-label" for="visible_to_driver">Visible to drivers</label>
              </div>
              <button type="submit" class="btn btn-primary">
                <i class="ti tabler-plus me-1"></i>Add Note
              </button>
            </div>
          </form>

          <hr />

          {{-- Thread --}}
          @forelse($client->notes as $note)
            <div class="d-flex gap-3 {{ ! $loop->last ? 'mb-4' : '' }}">
              <div class="avatar avatar-sm flex-shrink-0">
                <span class="avatar-initial rounded-circle bg-label-secondary">
                  {{ strtoupper(substr($note->authorName(), 0, 1)) }}
                </span>
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <span class="fw-semibold">{{ $note->authorName() }}</span>
                  <small class="text-muted" title="{{ $note->created_at->format('d M Y H:i') }}">
                    {{ $note->created_at->diffForHumans() }}
                  </small>
                  @unless($note->visible_to_driver)
                    <span class="badge bg-label-secondary" title="Not shown to drivers">
                      <i class="ti tabler-eye-off icon-xs me-1"></i>Internal
                    </span>
                  @endunless
                  @if($note->updated_at->gt($note->created_at))
                    <small class="text-muted fst-italic">edited</small>
                  @endif
                </div>

                <p class="mb-2 text-break">{{ $note->body }}</p>

                <div class="d-flex gap-3">
                  <button type="button" class="btn btn-text-secondary btn-sm p-0"
                          data-bs-toggle="modal" data-bs-target="#editNote{{ $note->id }}">
                    <i class="ti tabler-pencil icon-xs me-1"></i>Edit
                  </button>
                  <form method="POST" action="{{ route('dispatcher.client.note.delete', [$client->id, $note->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-text-danger btn-sm p-0"
                            onclick="return confirm('Delete this note?')">
                      <i class="ti tabler-trash icon-xs me-1"></i>Delete
                    </button>
                  </form>
                </div>
              </div>
            </div>
          @empty
            <div class="text-center py-4 text-muted">
              <i class="ti tabler-notes fs-2 d-block mb-2 text-secondary"></i>
              <p class="mb-0 small">No notes yet. The first one you add appears here and on the driver's trip screen.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Trip history --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Recent Trips</h5>
          <span class="badge bg-label-secondary">{{ $tripCount }} total</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Trip</th>
                <th class="d-none d-md-table-cell">Pickup</th>
                <th class="d-none d-sm-table-cell">Driver</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
              @forelse($trips as $trip)
              @php($tripStatus = $trip->statusEnum())
              <tr>
                <td>
                  <span class="fw-semibold d-block">{{ $trip->reference() }}</span>
                  <small class="text-muted d-md-none">
                    {{ $trip->pickup_date?->format('d M Y') }}
                    @if($trip->driver) &middot; {{ $trip->driver->name }} @endif
                  </small>
                </td>
                <td class="d-none d-md-table-cell">
                  <span class="text-body">{{ $trip->pickup_date?->format('d M Y') }}</span>
                  <small class="text-muted d-block text-truncate" style="max-width: 260px;">
                    {{ $trip->pickup_address }}
                  </small>
                </td>
                <td class="d-none d-sm-table-cell">{{ $trip->driver?->name ?? '—' }}</td>
                <td>
                  <span class="badge {{ $tripStatus?->badgeClass() ?? 'bg-label-secondary' }}">
                    {{ $tripStatus?->label() ?? 'Unknown' }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ route('dispatcher.trip.details', $trip->id) }}"
                     class="btn btn-sm btn-label-primary" aria-label="View {{ $trip->reference() }}">
                    <i class="ti tabler-eye"></i>
                  </a>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                  This client has no trips yet.
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Edit-note modals --}}
@foreach($client->notes as $note)
<div class="modal fade" id="editNote{{ $note->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST"
          action="{{ route('dispatcher.client.note.update', [$client->id, $note->id]) }}">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit note</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="note-body-{{ $note->id }}">Note</label>
        <textarea id="note-body-{{ $note->id }}" name="body" rows="4" maxlength="2000"
                  class="form-control" required>{{ $note->body }}</textarea>
        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" value="1"
                 id="note-visible-{{ $note->id }}" name="visible_to_driver"
                 @checked($note->visible_to_driver) />
          <label class="form-check-label" for="note-visible-{{ $note->id }}">Visible to drivers</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
@endforeach
@endsection
