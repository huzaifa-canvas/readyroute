{{--
  Realtime for the dispatcher panel.

  The socket is deliberately not a second way of rendering anything. It only
  says "something changed, fetch now", and the pages refresh through the same
  code their polling already used. That keeps one rendering path, so a message
  cannot arrive twice or render differently depending on how it reached the
  page — and it means the panel still works exactly as before when the socket
  is unavailable, just a few seconds slower.

  Nothing is attempted unless SOCKET_PUBLIC_URL is set, so this is inert until
  the socket server has a public address.
--}}
@if(auth()->check() && auth()->user()->isDispatcher() && filled(config('socket.public_url')))
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js" crossorigin="anonymous"></script>
<script>
  (function () {
    // Pages ask this whether to keep polling hard or to back off.
    window.readyRouteRealtime = { connected: false };

    const tokenUrl = @json(route('dispatcher.socket-token'));

    function announce(event, payload) {
      window.dispatchEvent(new CustomEvent('rr:' + event, { detail: payload }));
    }

    async function start() {
      if (typeof io === 'undefined') {
        // The CDN did not load. Polling carries the panel on its own.
        return;
      }

      let config;

      try {
        // no-store, or the browser can serve a cached copy and keep handing
        // back the address the socket server used to be on after it moves.
        const response = await fetch(tokenUrl, {
          headers: { 'Accept': 'application/json' },
          cache: 'no-store',
        });
        if (!response.ok) return;
        config = await response.json();
      } catch (error) {
        return;
      }

      if (!config || !config.enabled || !config.url || !config.token) return;

      const socket = io(config.url, {
        auth: { token: config.token },
        transports: ['websocket', 'polling'],
        // Give up after a while rather than reconnecting for ever behind a
        // blocked port: polling is already covering the page.
        reconnectionAttempts: 10,
        reconnectionDelayMax: 10000,
        timeout: 8000,
      });

      socket.on('connect', function () {
        window.readyRouteRealtime.connected = true;
        announce('connected', {});
      });

      socket.on('disconnect', function () {
        window.readyRouteRealtime.connected = false;
        announce('disconnected', {});
      });

      socket.on('connect_error', function () {
        window.readyRouteRealtime.connected = false;
      });

      // Everything the server pushes becomes a DOM event any page can listen
      // for, so a page never has to know a socket exists.
      ['message:new', 'message:read', 'notification:new', 'trip:assigned',
       'trip:cancelled', 'incident:new', 'incident:acknowledged', 'incident:resolved']
        .forEach(function (event) {
          socket.on(event, function (payload) { announce(event, payload); });
        });

      window.readyRouteRealtime.socket = socket;
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', start);
    } else {
      start();
    }
  })();
</script>
@endif
