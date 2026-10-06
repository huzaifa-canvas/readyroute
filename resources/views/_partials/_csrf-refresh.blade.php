{{--
  Keeps the CSRF token on a standalone form page current.

  The sign-in screens have no polling of their own, so one left open past the
  session lifetime submits a dead token and fails with 419. The token is
  renewed when the tab comes back into view after a while, and on a timer while
  it stays open. If this ever misses, the 419 handler in bootstrap/app.php
  still returns the user to the form rather than an error page.
--}}
<script>
  (function () {
    const REFRESH_AFTER_MS = 10 * 60 * 1000;
    let lastRefresh = Date.now();

    function refresh() {
      lastRefresh = Date.now();

      fetch(@json(route('csrf.token')), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
          if (!data || !data.token) return;

          document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = data.token;
          });

          const meta = document.querySelector('meta[name="csrf-token"]');
          if (meta) meta.setAttribute('content', data.token);
        })
        .catch(function () { /* the server-side fallback covers a failed refresh */ });
    }

    function refreshIfStale() {
      if (!document.hidden && Date.now() - lastRefresh >= REFRESH_AFTER_MS) refresh();
    }

    document.addEventListener('visibilitychange', refreshIfStale);
    window.addEventListener('focus', refreshIfStale);
    setInterval(refreshIfStale, REFRESH_AFTER_MS);
  })();
</script>
