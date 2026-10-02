@php
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Facades\Route;
@endphp

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
@if (isset($navbarFull))
  <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4 ms-0">
    <a href="{{ url('/') }}" class="app-brand-link">
      <span class="app-brand-logo demo">@include('_partials.macros')</span>
      <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
    </a>

    <!-- Display menu close icon only for horizontal-menu with navbar-full -->
    @if (isset($menuHorizontal))
      <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
        <i class="icon-base ti tabler-x icon-sm d-flex align-items-center justify-content-center"></i>
      </a>
    @endif
  </div>
@endif

<!-- ! Not required for layout-without-menu -->
@if (!isset($navbarHideToggle))
  <div
    class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0{{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
      <i class="icon-base ti tabler-menu-2 icon-md"></i>
    </a>
  </div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

  {{-- Search palette. The toggler is all Vuexy needs; main.js builds the
       Ctrl+K popup and fills it from /assets/json/search-vertical.json,
       which this app serves per role. --}}
  <div class="navbar-nav align-items-center flex-grow-1">
    <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0 w-100">
      <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
        <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
      </a>
    </div>
  </div>

  <ul class="navbar-nav flex-row align-items-center ms-auto">

    @if ($configData['hasCustomizer'] == true)
    <!-- Style Switcher -->
    <li class="nav-item dropdown me-2 me-xl-1">
      <a class="nav-link dropdown-toggle hide-arrow" id="nav-theme" href="javascript:void(0);"
          data-bs-toggle="dropdown">
          {{-- text-heading, like the messages and notification icons beside
               it. Without it this is the one navbar glyph whose colour is
               inherited from the link rather than pinned to the theme, so any
               state that changes the link's colour takes the icon with it.
               Safe to add: the template rewrites this element's class list
               when the theme changes, but it keeps everything that is not a
               tabler-* class. --}}
          <i class="icon-base ti tabler-sun icon-md theme-icon-active text-heading"></i>
          <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
          <li>
            <button type="button" class="dropdown-item align-items-center active" data-bs-theme-value="light"
              aria-pressed="false">
              <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i>Light</span>
            </button>
          </li>
          <li>
            <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="dark"
              aria-pressed="true">
              <span><i class="icon-base ti tabler-moon-stars icon-22px me-3" data-icon="moon-stars"></i>Dark</span>
            </button>
          </li>
          <li>
            <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="system"
              aria-pressed="false">
              <span><i class="icon-base ti tabler-device-desktop-analytics icon-22px me-3"
                  data-icon="device-desktop-analytics"></i>System</span>
            </button>
          </li>
        </ul>
      </li>
    <!-- / Style Switcher-->
    @endif


    @php($navNotifications = auth()->check() ? auth()->user()->notifications()->latest()->limit(7)->get() : collect())
    @php($navUnread = $navNotifications->whereNull('read_at')->count())

    {{-- Driver chat and notifications belong to a company. An admin has no
         company, so these are dispatcher-only; the search palette stays for
         both, because it is built per role. --}}
    @if(auth()->check() && auth()->user()->isDispatcher())

    @php($navUnreadMessages = auth()->check()
        ? \App\Models\Message::where('dispatcher_id', auth()->user()->companyId())
            ->where('receiver_id', auth()->user()->companyId())
            ->whereNull('read_at')->count()
        : 0)

    {{-- The count badges sit on the list item, not inside the button.

         Vuexy's own bell carries a `badge-dot` — a dot small enough to sit
         well inside the round button — so the stock template never runs into
         this. A badge with a number in it is 20px and reaches past the
         circle's edge, where node-waves' `overflow: hidden` (which keeps the
         click ripple inside the button) and the pill radius cut the corner
         off it. Anchored one level up there is nothing to clip it, the button
         keeps its ripple, and `pointer-events: none` leaves the whole icon a
         single click target. --}}

    <!-- Driver messages -->
    <li class="nav-item me-2 me-xl-1 position-relative">
      <a class="nav-link btn btn-icon btn-text-secondary rounded-pill"
         href="{{ route('dispatcher.messages.index') }}"
         aria-label="Driver messages" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Driver messages">
        <i class="icon-base ti tabler-message-circle icon-22px text-heading"></i>
      </a>
      {{-- Hidden at zero rather than removed, so the poller can reveal it
           without rebuilding the markup. --}}
      <span id="navMessageCount"
            class="badge rounded-pill bg-danger badge-center h-px-20 position-absolute {{ $navUnreadMessages > 0 ? '' : 'd-none' }}"
            style="top:-2px; inset-inline-end:-2px; font-size:.6875rem; min-inline-size:20px; inline-size:auto; padding-inline:.3rem; pointer-events:none;">{{ $navUnreadMessages > 99 ? '99+' : $navUnreadMessages }}</span>
    </li>
    <!--/ Driver messages -->

    <!-- Notifications -->
    <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-2 me-xl-1">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
         href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside"
         aria-expanded="false" aria-label="Notifications">
        <i class="icon-base ti tabler-bell icon-22px text-heading"></i>
      </a>
      <span id="navNotificationCount"
            class="badge rounded-pill bg-danger badge-center h-px-20 position-absolute {{ $navUnread > 0 ? '' : 'd-none' }}"
            style="top:-2px; inset-inline-end:-2px; font-size:.6875rem; min-inline-size:20px; inline-size:auto; padding-inline:.3rem; pointer-events:none;">{{ $navUnread > 99 ? '99+' : $navUnread }}</span>

      <ul class="dropdown-menu dropdown-menu-end p-0">
        <li class="dropdown-menu-header border-bottom">
          <div class="dropdown-header d-flex align-items-center py-3">
            <h6 class="mb-0 me-auto">Notification</h6>
            <div class="d-flex align-items-center h6 mb-0">
              <span id="navNotificationNew" class="badge bg-label-primary me-2 {{ $navUnread > 0 ? '' : 'd-none' }}">{{ $navUnread }} New</span>
              @if($navUnread > 0)
                <form method="POST" action="{{ route('dispatcher.notifications.read-all') }}" class="d-inline">
                  @csrf
                  <button type="submit" class="dropdown-notifications-all p-2 btn btn-icon border-0 bg-transparent"
                          data-bs-toggle="tooltip" data-bs-placement="top" title="Mark all as read"
                          aria-label="Mark all as read">
                    <i class="icon-base ti tabler-mail-opened text-heading"></i>
                  </button>
                </form>
              @endif
            </div>
          </div>
        </li>

        <li class="dropdown-notifications-list scrollable-container">
          <ul class="list-group list-group-flush">
            @forelse($navNotifications as $note)
              @php($kind = $note->data['kind'] ?? 'general')
              @php($data = $note->data['data'] ?? [])
              @php($icon = match ($kind) {
                    'sos'            => ['tabler-urgent', 'danger'],
                    'incident'       => ['tabler-alert-triangle', 'warning'],
                    'new_message'    => ['tabler-message-circle', 'info'],
                    'trip_added'     => ['tabler-calendar-plus', 'primary'],
                    'route_change'   => ['tabler-route-2', 'warning'],
                    'trip_cancelled' => ['tabler-calendar-x', 'danger'],
                    default          => ['tabler-bell', 'secondary'],
                  })
              @php($target = match (true) {
                    ! empty($data['incident_id']) => route('dispatcher.incidents.show', $data['incident_id']),
                    ! empty($data['trip_id'])     => route('dispatcher.trip.details', $data['trip_id']),
                    ! empty($data['driver_id'])   => route('dispatcher.messages.index', ['driver' => $data['driver_id']]),
                    default                       => route('dispatcher.notifications.index'),
                  })

              <li class="list-group-item list-group-item-action dropdown-notifications-item {{ $note->read_at ? 'marked-as-read' : '' }}">
                <a href="{{ $target }}" class="d-flex text-body text-decoration-none">
                  <div class="flex-shrink-0 me-3">
                    <div class="avatar">
                      <span class="avatar-initial rounded-circle bg-label-{{ $icon[1] }}">
                        <i class="icon-base ti {{ $icon[0] }}"></i>
                      </span>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="small mb-1">{{ $note->data['title'] ?? 'Notification' }}</h6>
                    <small class="mb-1 d-block text-body">{{ \Illuminate\Support\Str::limit($note->data['body'] ?? '', 64) }}</small>
                    <small class="text-body-secondary">{{ $note->created_at->diffForHumans() }}</small>
                  </div>
                </a>
              </li>
            @empty
              <li class="list-group-item text-center py-5 text-body-secondary">
                <i class="icon-base ti tabler-bell-off icon-32px d-block mb-2"></i>
                <small>Nothing yet</small>
              </li>
            @endforelse
          </ul>
        </li>

        <li class="border-top">
          <div class="d-grid p-4">
            <a class="btn btn-primary btn-sm d-flex" href="{{ route('dispatcher.notifications.index') }}">
              <small class="align-middle">View all notifications</small>
            </a>
          </div>
        </li>
      </ul>
    </li>
    <!--/ Notifications -->

    @endif

    <!-- User -->
    <li class="nav-item navbar-dropdown dropdown-user dropdown">
      <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
        <div class="avatar avatar-online">
          <img src="{{ Auth::user() ? Auth::user()->avatar_url : asset('assets/img/avatars/1.png') }}" alt
            class="rounded-circle" />
        </div>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li>
          <a class="dropdown-item mt-0"
            href="javascript:void(0);">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0 me-2">
                <div class="avatar avatar-online">
                  <img src="{{ Auth::user() ? Auth::user()->avatar_url : asset('assets/img/avatars/1.png') }}"
                    alt class="rounded-circle" />
                </div>
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-0">
                  @if (Auth::check())
                    {{ Auth::user()->name }}
                  @else
                    John Doe
                  @endif
                </h6>
                <small class="text-body-secondary">
                  @if(Auth::check())
                    {{ Auth::user()->isAdmin() ? 'Administrator' : (Auth::user()->isCompanyOwner() ? 'Company Account' : (Auth::user()->accessRole?->name ?? 'Dispatcher')) }}
                  @else
                    Guest
                  @endif
                </small>
              </div>
            </div>
          </a>
        </li>
        <li>
          <div class="dropdown-divider my-1 mx-n2"></div>
        </li>
        @if(auth()->check() && auth()->user()->isDispatcher())
        <li>
          <a class="dropdown-item" href="{{ route('dispatcher.profile.index') }}">
            <i class="icon-base ti tabler-user me-3 icon-md"></i><span class="align-middle">My Profile</span> </a>
        </li>
        @endif

        @if(auth()->check() && auth()->user()->isDispatcher())
        <li>
          <a class="dropdown-item" href="{{ route('dispatcher.billing.index') }}">
            <i class="icon-base ti tabler-file-dollar me-3 icon-md"></i><span class="align-middle">Billing &amp; Claims</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="{{ route('dispatcher.subscription') }}">
            <i class="icon-base ti tabler-credit-card me-3 icon-md"></i><span class="align-middle">My Subscription</span>
          </a>
        </li>
        @elseif(auth()->check() && auth()->user()->isAdmin())
        <li>
          <a class="dropdown-item" href="{{ route('admin.security') }}">
            <i class="icon-base ti tabler-shield-lock me-3 icon-md"></i><span class="align-middle">Platform Security</span>
          </a>
        </li>
        @endif

        <li>
          <div class="dropdown-divider my-1 mx-n2"></div>
        </li>
        @if (Auth::check())
          <li>
            <a class="dropdown-item" href="{{ route('logout') }}"
              onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
              <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Logout</span>
            </a>
          </li>
          <form method="POST" id="logout-form" action="{{ route('logout') }}">
            @csrf
          </form>
        @else
          <li>
            <div class="d-grid px-2 pt-2 pb-1">
              <a class="btn btn-sm btn-danger d-flex"
                href="{{ Route::has('login') ? route('login') : url('auth/login-basic') }}" target="_blank">
                <small class="align-middle">Login</small>
                <i class="icon-base ti tabler-login ms-2 icon-14px"></i>
              </a>
            </div>
          </li>
        @endif
      </ul>
    </li>
    <!--/ User -->
  </ul>
</div>

{{-- Live navbar badges.
     One request every 30 seconds refreshes the message count, the
     notification count and, when the chat is open, the per-driver counts in
     its sidebar. Dispatcher-only, because the endpoint is. --}}
@if(auth()->check() && auth()->user()->isDispatcher())
<script>
  (function () {
    var url = @json(route('dispatcher.navbar.summary'));

    function paint(el, count) {
      if (!el) return;
      el.textContent = count > 99 ? '99+' : count;
      el.classList.toggle('d-none', count < 1);
    }

    function title(base, count) {
      document.title = count > 0 ? '(' + count + ') ' + base : base;
    }

    var baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');

    function renderNotifications(list) {
      var container = document.querySelector('.dropdown-notifications-list .list-group');
      if (!container || !list) return;

      // Leave the open dropdown alone: replacing it under the cursor would
      // move whatever the person is about to click.
      if (document.querySelector('.dropdown-notifications .dropdown-menu.show')) return;

      if (list.length === 0) return;

      container.innerHTML = list.map(function (n) {
        var icon = ({
          sos: ['tabler-urgent', 'danger'],
          incident: ['tabler-alert-triangle', 'warning'],
          new_message: ['tabler-message-circle', 'info'],
          trip_added: ['tabler-calendar-plus', 'primary'],
          route_change: ['tabler-route-2', 'warning'],
          trip_cancelled: ['tabler-calendar-x', 'danger']
        })[n.kind] || ['tabler-bell', 'secondary'];

        var div = document.createElement('div');
        div.textContent = n.title;
        var safeTitle = div.innerHTML;
        div.textContent = n.body;
        var safeBody = div.innerHTML;

        return '<li class="list-group-item list-group-item-action dropdown-notifications-item' +
               (n.is_read ? ' marked-as-read' : '') + '">' +
                 '<a href="' + n.url + '" class="d-flex text-body text-decoration-none">' +
                   '<div class="flex-shrink-0 me-3"><div class="avatar">' +
                     '<span class="avatar-initial rounded-circle bg-label-' + icon[1] + '">' +
                       '<i class="icon-base ti ' + icon[0] + '"></i></span>' +
                   '</div></div>' +
                   '<div class="flex-grow-1">' +
                     '<h6 class="small mb-1">' + safeTitle + '</h6>' +
                     '<small class="mb-1 d-block text-body">' + safeBody + '</small>' +
                     '<small class="text-body-secondary">' + n.ago + '</small>' +
                   '</div>' +
                 '</a>' +
               '</li>';
      }).join('');
    }

    function renderDriverCounts(perDriver) {
      // Only present while the chat page is open.
      document.querySelectorAll('[data-unread-driver]').forEach(function (badge) {
        var count = perDriver[badge.dataset.unreadDriver] || 0;
        badge.textContent = count;
        badge.classList.toggle('d-none', count < 1);
      });
    }

    async function tick() {
      // Nothing to refresh while the tab is in the background.
      if (document.hidden) return;

      try {
        var response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;

        var data = (await response.json()).data;

        paint(document.getElementById('navMessageCount'), data.messages.unread);
        paint(document.getElementById('navNotificationCount'), data.notifications.unread);

        var chip = document.getElementById('navNotificationNew');
        if (chip) {
          chip.textContent = data.notifications.unread + ' New';
          chip.classList.toggle('d-none', data.notifications.unread < 1);
        }

        renderNotifications(data.notifications.recent);
        renderDriverCounts(data.messages.per_driver || {});

        // The tab title carries whichever queue needs attention most.
        title(baseTitle, data.messages.unread + data.notifications.unread);
      } catch (error) {
        // A dropped poll is not worth surfacing; the next tick catches up.
      }
    }

    /*
     * Polling stays, as the thing that keeps the counts right when the socket
     * is down. When the socket is up it only has to cover the gap left by a
     * missed event, so it runs far less often.
     */
    var FAST_POLL = 30000;
    var SLOW_POLL = 120000;
    var timer = setInterval(tick, FAST_POLL);

    function repoll(every) {
      clearInterval(timer);
      timer = setInterval(tick, every);
    }

    window.addEventListener('rr:connected', function () { repoll(SLOW_POLL); });
    window.addEventListener('rr:disconnected', function () { repoll(FAST_POLL); tick(); });

    // A new message or notification refreshes the counts straight away,
    // through the same request the poll uses rather than a second code path.
    ['rr:message:new', 'rr:notification:new', 'rr:incident:new'].forEach(function (event) {
      window.addEventListener(event, function () { tick(); });
    });

    // Catch up immediately when the tab comes back rather than waiting out
    // the rest of the interval.
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) tick();
    });
  })();
</script>
@endif
