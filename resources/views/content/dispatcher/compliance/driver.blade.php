@extends('layouts/layoutMaster')

@section('title', 'Documents — ' . $driver->name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('dispatcher.compliance') }}">Compliance Center</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $driver->name }}</li>
    </ol>
  </nav>

  {{-- Driver header --}}
  <div class="card mb-4">
    <div class="card-body d-flex flex-column flex-sm-row align-items-sm-center gap-3">
      <div class="avatar avatar-lg flex-shrink-0 mx-auto mx-sm-0">
        <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
      </div>
      <div class="flex-grow-1 text-center text-sm-start min-w-0">
        <h5 class="fw-bold mb-1 text-break">{{ $driver->name }}</h5>
        <div class="d-flex flex-wrap justify-content-center justify-content-sm-start gap-3 text-muted small">
          <span>{{ $driver->driver_code }}</span>
          <span class="text-break">{{ $driver->email }}</span>
          @if($driver->phone_number)
            <span>{{ $driver->phone_number }}</span>
          @endif
        </div>
      </div>
      <div class="d-flex gap-2 justify-content-center justify-content-sm-end flex-shrink-0">
        <a href="{{ route('dispatcher.driver.edit', $driver->id) }}" class="btn btn-label-primary">
          <i class="ti tabler-user me-1"></i>Profile
        </a>
      </div>
    </div>
  </div>

  <div class="row g-4">

    {{-- Documents --}}
    <div class="col-12 col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Documents</h5>
          <span class="badge bg-label-secondary">{{ $documents->count() }}</span>
        </div>

        <div class="card-body">
          @forelse($documents as $document)
            <div class="border rounded p-3 {{ ! $loop->last ? 'mb-3' : '' }}">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div class="min-w-0">
                  <h6 class="mb-1 text-break">{{ $document->typeLabel() }}</h6>
                  @if($document->reference)
                    <small class="text-muted d-block">Ref: {{ $document->reference }}</small>
                  @endif
                </div>
                <span class="badge {{ $document->statusClass() }} flex-shrink-0">
                  {{ $document->statusLabel() }}
                </span>
              </div>

              <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
                @if($document->issued_on)
                  <span><i class="ti tabler-calendar-plus icon-xs me-1"></i>Issued {{ $document->issued_on->format('d M Y') }}</span>
                @endif
                @if($document->expires_on)
                  @php($days = $document->daysUntilExpiry())
                  <span class="{{ $days !== null && $days < 0 ? 'text-danger' : ($days !== null && $days <= 30 ? 'text-warning' : '') }}">
                    <i class="ti tabler-calendar-x icon-xs me-1"></i>
                    Expires {{ $document->expires_on->format('d M Y') }}
                    @if($days !== null)
                      ({{ $days < 0 ? abs($days) . ' days ago' : 'in ' . $days . ' days' }})
                    @endif
                  </span>
                @else
                  <span><i class="ti tabler-infinity icon-xs me-1"></i>No expiry</span>
                @endif
              </div>

              @if($document->notes)
                <p class="text-muted small mb-2 text-break">{{ $document->notes }}</p>
              @endif

              <div class="d-flex flex-wrap gap-2">
                @if($document->file_path)
                  <a href="{{ route('dispatcher.compliance.download', [$driver->id, $document->id]) }}"
                     class="btn btn-sm btn-label-primary">
                    <i class="ti tabler-download me-1"></i>Download
                  </a>
                @endif
                <button type="button" class="btn btn-sm btn-label-secondary"
                        data-bs-toggle="modal" data-bs-target="#editDoc{{ $document->id }}">
                  <i class="ti tabler-edit me-1"></i>Edit
                </button>
                <form method="POST" action="{{ route('dispatcher.compliance.delete', [$driver->id, $document->id]) }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-label-danger"
                          onclick="return confirm('Remove this document?')">
                    <i class="ti tabler-trash me-1"></i>Remove
                  </button>
                </form>
              </div>

              @if($document->uploader)
                <small class="text-muted d-block mt-2">
                  Added by {{ $document->uploader->name }} {{ $document->created_at->diffForHumans() }}
                </small>
              @endif
            </div>
          @empty
            <div class="text-center py-5 text-muted">
              <i class="ti tabler-file-off fs-1 d-block mb-2 text-secondary"></i>
              <h6>No documents on file</h6>
              <p class="mb-0 small">Add this driver's licence and medical card to start tracking expiry.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Add form --}}
    <div class="col-12 col-lg-5">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Add Document</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('dispatcher.compliance.store', $driver->id) }}"
                enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
              <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
              <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                @foreach(\App\Models\DriverDocument::TYPES as $key => $label)
                  <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                @endforeach
              </select>
              @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="label">Label</label>
              <input type="text" id="label" name="label" class="form-control"
                     placeholder="Leave blank to use the type name" value="{{ old('label') }}" />
            </div>

            <div class="mb-3">
              <label class="form-label" for="reference">Reference number</label>
              <input type="text" id="reference" name="reference" class="form-control"
                     placeholder="Licence or policy number" value="{{ old('reference') }}" />
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label" for="issued_on">Issued</label>
                <input type="date" id="issued_on" name="issued_on" class="form-control"
                       value="{{ old('issued_on') }}" />
              </div>
              <div class="col-6">
                <label class="form-label" for="expires_on">Expires</label>
                <input type="date" id="expires_on" name="expires_on"
                       class="form-control @error('expires_on') is-invalid @enderror"
                       value="{{ old('expires_on') }}" />
                @error('expires_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="file">Scan or photo</label>
              <input type="file" id="file" name="file"
                     class="form-control @error('file') is-invalid @enderror"
                     accept=".pdf,.jpg,.jpeg,.png" />
              <small class="text-muted">PDF or image, up to 5 MB. Stored privately.</small>
              @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="notes">Notes</label>
              <textarea id="notes" name="notes" rows="2" class="form-control"
                        maxlength="2000">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="ti tabler-plus me-1"></i>Add Document
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Edit modals --}}
@foreach($documents as $document)
<div class="modal fade" id="editDoc{{ $document->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="POST"
          action="{{ route('dispatcher.compliance.update', [$driver->id, $document->id]) }}"
          enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit {{ $document->typeLabel() }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="edit-type-{{ $document->id }}">Type</label>
          <select id="edit-type-{{ $document->id }}" name="type" class="form-select" required>
            @foreach(\App\Models\DriverDocument::TYPES as $key => $label)
              <option value="{{ $key }}" @selected($document->type === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit-label-{{ $document->id }}">Label</label>
          <input type="text" id="edit-label-{{ $document->id }}" name="label"
                 class="form-control" value="{{ $document->label }}" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit-ref-{{ $document->id }}">Reference number</label>
          <input type="text" id="edit-ref-{{ $document->id }}" name="reference"
                 class="form-control" value="{{ $document->reference }}" />
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label" for="edit-issued-{{ $document->id }}">Issued</label>
            <input type="date" id="edit-issued-{{ $document->id }}" name="issued_on"
                   class="form-control" value="{{ optional($document->issued_on)->toDateString() }}" />
          </div>
          <div class="col-6">
            <label class="form-label" for="edit-expires-{{ $document->id }}">Expires</label>
            <input type="date" id="edit-expires-{{ $document->id }}" name="expires_on"
                   class="form-control" value="{{ optional($document->expires_on)->toDateString() }}" />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="edit-file-{{ $document->id }}">Replace file</label>
          <input type="file" id="edit-file-{{ $document->id }}" name="file"
                 class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
          <small class="text-muted">
            {{ $document->file_path ? 'Leave empty to keep the current file.' : 'No file attached yet.' }}
          </small>
        </div>
        <div class="mb-0">
          <label class="form-label" for="edit-notes-{{ $document->id }}">Notes</label>
          <textarea id="edit-notes-{{ $document->id }}" name="notes" rows="2"
                    class="form-control" maxlength="2000">{{ $document->notes }}</textarea>
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
