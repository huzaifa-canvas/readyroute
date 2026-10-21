{{-- Edit and remove for one maintenance row. Shared by the open-work table
     and the history table so the two cannot drift apart. --}}
<button type="button" class="btn btn-icon btn-text-primary rounded-pill"
        title="Edit this record"
        data-bs-toggle="modal" data-bs-target="#logModal"
        onclick="rrEditLog(this)"
        data-id="{{ $log->id }}"
        data-title="{{ $log->title }}"
        data-type="{{ $log->type }}"
        data-status="{{ $log->status }}"
        data-performed="{{ optional($log->performed_on)->toDateString() }}"
        data-due="{{ optional($log->due_on)->toDateString() }}"
        data-vendor="{{ $log->vendor }}"
        data-cost="{{ $log->cost }}"
        data-odometer="{{ $log->odometer }}"
        data-description="{{ $log->description }}">
  <i class="ti tabler-pencil"></i>
</button>

<form action="{{ route('dispatcher.fleet.maintenance.delete', [$vehicle->id, $log->id]) }}" method="POST" class="d-inline">
  @csrf
  @method('DELETE')
  <button type="submit" class="btn btn-icon btn-text-danger rounded-pill"
          title="Remove this record"
          onclick="return confirm('Remove this maintenance record? The history will no longer show it.')">
    <i class="ti tabler-trash"></i>
  </button>
</form>
