{{--
  Double-booking warning.

  Shown when a driver being assigned already has another active trip within the
  configured window of this pickup. It only warns — the dispatcher decides: pick
  someone else, or force this driver onto both. The page that includes it wires
  the two buttons (the dashboard assigns over AJAX; the create / edit forms
  pre-check and then submit).
--}}
<div class="modal fade" id="assignConflictModal" tabindex="-1" aria-labelledby="assignConflictTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold text-warning" id="assignConflictTitle">
          <i class="ti tabler-alert-triangle me-1"></i> Possible Double-Booking
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4">
        <p class="mb-2" id="assignConflictMessage">This driver already has a trip near this time.</p>
        <ul class="list-unstyled mb-0 small text-muted" id="assignConflictList"></ul>
        <div class="alert alert-warning mt-3 mb-0 small">
          <i class="ti tabler-info-circle me-1"></i>
          <strong>Force Assign</strong> will put this driver on both trips and ignore the time-gap limit.
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-outline-secondary" id="assignConflictChangeBtn">
          <i class="ti tabler-user-search me-1"></i> Change Driver
        </button>
        <button type="button" class="btn btn-danger" id="assignConflictForceBtn">
          <i class="ti tabler-bolt me-1"></i> Force Assign
        </button>
      </div>
    </div>
  </div>
</div>
