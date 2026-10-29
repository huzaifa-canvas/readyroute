{{--
  Cancel-with-reason dialog.

  A cancelled trip is kept on the record, so cancelling asks why first. One
  modal serves the whole page — the trip list opens it per row and the detail
  page opens it for the one trip — so its form action is set as it opens.
  "Other" reveals a note, which is then required. The cancel controllers enforce
  the same rule, so turning JS off cannot cancel without a reason.
--}}
<div class="modal fade" id="cancelTripModal" tabindex="-1" aria-labelledby="cancelTripTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="cancelTripForm" method="POST" action="">
        @csrf
        @method('PATCH')
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold text-warning" id="cancelTripTitle">
            <i class="ti tabler-ban me-1"></i> Cancel Trip <span id="cancelTripRef" class="text-body"></span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body py-3">
          <p class="text-muted small mb-3">
            This keeps the trip on the record and notifies the assigned driver. Please choose why it is being cancelled.
          </p>
          <label class="form-label fw-semibold mb-2">Reason For Cancellation <span class="text-danger">*</span></label>
          <div>
            @foreach(\App\Enums\CancellationReason::cases() as $reason)
              <div class="form-check mb-2">
                <input class="form-check-input cancel-reason-input" type="radio"
                       name="cancellation_reason" value="{{ $reason->value }}"
                       id="cancel_reason_{{ $reason->value }}"
                       data-needs-note="{{ $reason->needsNote() ? '1' : '0' }}" required>
                <label class="form-check-label" for="cancel_reason_{{ $reason->value }}">{{ $reason->label() }}</label>
              </div>
            @endforeach
          </div>
          <div class="mt-2" id="cancelNoteWrap" style="display: none;">
            <label class="form-label" for="cancellation_note">Please specify</label>
            <textarea class="form-control" name="cancellation_note" id="cancellation_note" rows="2"
                      maxlength="500" placeholder="Add a short reason"></textarea>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Trip</button>
          <button type="submit" class="btn btn-warning">
            <i class="ti tabler-ban me-1"></i> Cancel Trip
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  function init() {
    var modalEl = document.getElementById('cancelTripModal');
    var form    = document.getElementById('cancelTripForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    var noteWrap = document.getElementById('cancelNoteWrap');
    var noteBox  = document.getElementById('cancellation_note');

    function toggleNote() {
      var checked = form.querySelector('input[name="cancellation_reason"]:checked');
      var needs = checked && checked.dataset.needsNote === '1';
      if (noteWrap) noteWrap.style.display = needs ? 'block' : 'none';
      if (noteBox)  noteBox.required = !!needs;
    }

    form.querySelectorAll('.cancel-reason-input').forEach(function (el) {
      el.addEventListener('change', toggleNote);
    });

    // Open the dialog for one trip: point the form at its cancel URL, name the
    // trip in the title, and start from a clean slate every time.
    window.openCancelModal = function (actionUrl, reference) {
      form.setAttribute('action', actionUrl);
      var refEl = document.getElementById('cancelTripRef');
      if (refEl) refEl.textContent = reference || '';
      form.querySelectorAll('input[name="cancellation_reason"]').forEach(function (el) { el.checked = false; });
      if (noteBox) noteBox.value = '';
      toggleNote();
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
