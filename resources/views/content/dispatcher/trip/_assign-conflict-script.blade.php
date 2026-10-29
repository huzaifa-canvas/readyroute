{{--
  Drives the double-booking warning on the trip create / edit forms.

  As the dispatcher presses Save with a driver chosen, this asks the server
  whether that driver is already booked near the pickup. A clash opens the
  shared dialog (Force Assign / Change Driver); a clear answer lets the form
  submit normally. The server's store / update still enforce the rule, so this
  is only the friendly half — turning JS off cannot slip a clash through.
--}}
<script>
(function () {
  function init() {
    var form = document.getElementById('createTripForm') || document.getElementById('editTripForm');
    var conflictModalEl = document.getElementById('assignConflictModal');
    if (!form || !conflictModalEl || typeof bootstrap === 'undefined') return;

    var conflictModal = bootstrap.Modal.getOrCreateInstance(conflictModalEl);
    var driverSelect  = form.querySelector('[name="driver_id"]');
    var forceInput    = form.querySelector('[name="force_assign"]');
    var tokenInput    = form.querySelector('input[name="_token"]');
    var checkUrl      = @json(route('dispatcher.trip.assign-check'));

    // Edit posts to .../update/{id}; pull that id so a trip never clashes with
    // itself. Create posts to .../store, so there is no id and nothing to skip.
    var m = (form.getAttribute('action') || '').match(/(\d+)(?:\?.*)?$/);
    var excludeTripId = m ? m[1] : '';

    function fieldValue(name) {
      var el = form.querySelector('[name="' + name + '"]');
      return el ? el.value : '';
    }

    form.addEventListener('submit', function (e) {
      if (!driverSelect || !driverSelect.value) return;      // auto-assign / unassigned
      if (forceInput && forceInput.value === '1') return;     // already forced through

      e.preventDefault();

      var body = new URLSearchParams();
      body.set('driver_id', driverSelect.value);
      body.set('pickup_date', fieldValue('pickup_date'));
      body.set('pickup_time', fieldValue('pickup_time'));
      if (excludeTripId) body.set('exclude_trip_id', excludeTripId);

      fetch(checkUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': tokenInput ? tokenInput.value : '',
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body.toString(),
      }).then(function (res) {
        if (!res.ok) { form.submit(); return null; }          // never block on a check failure
        return res.json();
      }).then(function (data) {
        if (data && data.conflict) {
          renderConflict(data);
          conflictModal.show();
        } else if (data !== null) {
          form.submit();                                      // method call: skips this listener
        }
      }).catch(function () { form.submit(); });
    });

    function renderConflict(data) {
      var msgEl  = document.getElementById('assignConflictMessage');
      var listEl = document.getElementById('assignConflictList');
      if (msgEl)  msgEl.textContent = data.message || 'This driver already has a trip near this time.';
      if (listEl) {
        listEl.innerHTML = '';
        (data.conflicts || []).forEach(function (c) {
          var li = document.createElement('li');
          li.className = 'mb-1';
          li.textContent = c.reference + ' — ' + c.passenger +
            ' (' + (c.pickup_label || '') + ', ' + (c.relation || '') + ')';
          listEl.appendChild(li);
        });
      }
    }

    var forceBtn = document.getElementById('assignConflictForceBtn');
    if (forceBtn) forceBtn.addEventListener('click', function () {
      conflictModal.hide();
      if (forceInput) forceInput.value = '1';
      form.submit();
    });

    var changeBtn = document.getElementById('assignConflictChangeBtn');
    if (changeBtn) changeBtn.addEventListener('click', function () {
      conflictModal.hide();
      if (driverSelect) driverSelect.focus();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
