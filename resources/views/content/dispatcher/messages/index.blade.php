@extends('layouts/layoutMaster')

@section('title', $activeDriver ? 'Chat — ' . $activeDriver->name : 'Driver Messages')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
@endsection

@section('page-style')
<style>
  /*
    The template leaves 3rem between every message. That reads well in its demo,
    where the messages are paragraphs, but dispatch traffic is mostly one or two
    words — at 3rem apiece five messages filled 1170px and the conversation was
    mostly empty space. Messages sit closer together, and the larger gap is
    spent where it carries meaning: where the speaker changes.

    The selector repeats the template's own chain so it has equal weight and
    wins on source order, rather than being forced through with !important.
  */
  .app-chat .app-chat-history .chat-history-body .chat-history .chat-message:not(:last-child) {
    margin-block-end: .75rem;
  }

  .app-chat .chat-history .chat-message + .chat-message-right,
  .app-chat .chat-history .chat-message-right + .chat-message:not(.chat-message-right) {
    margin-block-start: 1.75rem;
  }

  /* A run from one speaker repeats their avatar on every line. Hide it after
     the first, keeping the space so the bubbles stay on one edge. */
  .app-chat .chat-history .chat-message:not(.chat-message-right) + .chat-message:not(.chat-message-right) .user-avatar .avatar,
  .app-chat .chat-history .chat-message-right + .chat-message-right .user-avatar .avatar {
    visibility: hidden;
  }

  /* Likewise the timestamp: the last message of a run carries the time for the
     whole run. The row is removed rather than just hidden, so a run of short
     replies actually closes up instead of leaving a blank line under each one.
     Every bubble still carries its own exact time in its title attribute, so
     one can be read without the layout moving. */
  .app-chat .chat-history .chat-message:has(+ .chat-message:not(.chat-message-right)):not(.chat-message-right) .chat-message-time,
  .app-chat .chat-history .chat-message-right:has(+ .chat-message-right) .chat-message-time {
    display: none;
  }

  /* The date divider is not a message and must not count as one for the rules
     above, or the first message of a day would lose its avatar. */
  .app-chat .chat-history .chat-date-divider {
    margin-block: 1.25rem;
  }

  /* Long words and pasted links must wrap rather than widen the bubble. */
  .app-chat .chat-history .chat-message-text {
    overflow-wrap: anywhere;
  }

  @media (max-width: 575.98px) {
    /* On a phone the bubbles get the width back that the avatars were using. */
    .app-chat .chat-history .chat-message .chat-message-wrapper {
      max-inline-size: 100%;
    }
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  <div class="app-chat card overflow-hidden">
    <div class="row g-0">

      {{-- ── Conversation list ──────────────────────────
           Vuexy's own contacts sidebar. It collapses behind the
           hamburger below lg, which is how the template handles phones. --}}
      <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
        <div class="sidebar-header h-px-75 px-5 border-bottom d-flex align-items-center">
          <div class="d-flex align-items-center me-6 me-lg-0 w-100">
            <div class="flex-shrink-0 avatar {{ auth()->user()->isCurrentlyOnline() ? 'avatar-online' : '' }} me-4">
              <img class="user-avatar rounded-circle" src="{{ auth()->user()->avatar_url }}" alt="Avatar" />
            </div>
            <div class="flex-grow-1 input-group input-group-merge">
              <span class="input-group-text" id="chat-search-addon">
                <i class="icon-base ti tabler-search icon-xs"></i>
              </span>
              <input type="text" class="form-control chat-search-input" placeholder="Search drivers..."
                     aria-label="Search drivers" aria-describedby="chat-search-addon" />
            </div>
          </div>
          <i class="icon-base ti tabler-x icon-lg cursor-pointer position-absolute top-50 end-0 translate-middle d-lg-none d-block"
             data-overlay data-bs-toggle="sidebar" data-target="#app-chat-contacts"></i>
        </div>

        <div class="sidebar-body">
          {{-- Drivers who have already written --}}
          <ul class="list-unstyled chat-contact-list py-2 mb-0" id="chat-list">
            <li class="chat-contact-list-item chat-contact-list-item-title mt-0">
              <h5 class="text-primary mb-0">Chats</h5>
            </li>
            <li class="chat-contact-list-item chat-list-item-0 d-none">
              <h6 class="text-body-secondary mb-0">No Chats Found</h6>
            </li>

            @forelse($conversations->filter(fn ($row) => $row->last_message !== null) as $row)
              @include('content.dispatcher.messages._contact', ['row' => $row, 'active' => $activeDriver?->id === $row->driver->id, 'showPreview' => true])
            @empty
              <li class="chat-contact-list-item">
                <h6 class="text-body-secondary mb-0">No conversations yet</h6>
              </li>
            @endforelse
          </ul>

          {{-- Everyone else, so a new conversation can be started --}}
          <ul class="list-unstyled chat-contact-list mb-0 py-2" id="contact-list">
            <li class="chat-contact-list-item chat-contact-list-item-title mt-0">
              <h5 class="text-primary mb-0">Drivers</h5>
            </li>
            <li class="chat-contact-list-item contact-list-item-0 d-none">
              <h6 class="text-body-secondary mb-0">No Drivers Found</h6>
            </li>

            @forelse($conversations->filter(fn ($row) => $row->last_message === null) as $row)
              @include('content.dispatcher.messages._contact', ['row' => $row, 'active' => $activeDriver?->id === $row->driver->id, 'showPreview' => false])
            @empty
              <li class="chat-contact-list-item">
                <h6 class="text-body-secondary mb-0">Everyone has an open chat</h6>
              </li>
            @endforelse
          </ul>
        </div>
      </div>

      {{-- ── Conversation ─────────────────────────────── --}}
      <div class="col app-chat-history" id="app-chat-history">
        @if(! $activeDriver)
          {{-- Vuexy's own placeholder for "no conversation selected" --}}
          <div class="bg-body d-flex align-items-center justify-content-center h-100">
            <div class="text-center">
              <span class="mb-4 badge bg-label-primary bg-lighter rounded-3">
                <i class="icon-base ti tabler-message-2 icon-48px"></i>
              </span>
              <h6 class="mb-0 text-body">
                <span class="cursor-pointer text-primary" data-bs-toggle="sidebar"
                      data-overlay data-target="#app-chat-contacts">Pick a driver</span>
                to start a conversation
              </h6>
            </div>
          </div>
        @else
          <div class="chat-history-wrapper">

            <div class="chat-history-header border-bottom">
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex overflow-hidden align-items-center">
                  <i class="icon-base ti tabler-menu-2 icon-lg cursor-pointer d-lg-none d-block me-4"
                     data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                  <div class="flex-shrink-0 avatar {{ $activeDriver->isCurrentlyOnline() ? 'avatar-online' : 'avatar-offline' }}">
                    <img src="{{ $activeDriver->avatar_url }}" alt="{{ $activeDriver->name }}" class="rounded-circle" />
                  </div>
                  <div class="chat-contact-info flex-grow-1 ms-4">
                    <h6 class="m-0 fw-normal">{{ $activeDriver->name }}</h6>
                    <small class="user-status text-body">
                      @if($activeDriver->isCurrentlyOnline())
                        Online
                      @elseif($activeDriver->last_seen_at)
                        Last seen {{ $activeDriver->last_seen_at->diffForHumans() }}
                      @else
                        {{ $activeDriver->driver_code }}
                      @endif
                    </small>
                  </div>
                </div>

                <div class="d-flex align-items-center">
                  @if($activeDriver->phone_number)
                    <a href="tel:{{ $activeDriver->phone_number }}"
                       class="btn btn-text-secondary cursor-pointer d-sm-inline-flex d-none me-1 btn-icon rounded-pill"
                       aria-label="Call {{ $activeDriver->name }}">
                      <i class="icon-base ti tabler-phone icon-22px"></i>
                    </a>
                  @endif
                  <div class="dropdown">
                    <button class="btn btn-icon btn-text-secondary text-secondary rounded-pill dropdown-toggle hide-arrow"
                            data-bs-toggle="dropdown" aria-expanded="false" id="chat-header-actions">
                      <i class="icon-base ti tabler-dots-vertical icon-22px"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="chat-header-actions">
                      <a class="dropdown-item" href="{{ route('dispatcher.driver.show', $activeDriver->id) }}">
                        View driver
                      </a>
                      <a class="dropdown-item" href="{{ route('dispatcher.compliance.driver', $activeDriver->id) }}">
                        Documents
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="chat-history-body">
              {{-- Only the newest page is drawn; older ones load when the
                   dispatcher scrolls to the top, or clicks this. --}}
              <div class="text-center mb-4 {{ $hasOlder ? '' : 'd-none' }}" id="load-older">
                <button type="button" class="btn btn-sm btn-label-secondary rounded-pill">
                  <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                  Load older messages
                </button>
              </div>

              <ul class="list-unstyled chat-history">
                @include('content.dispatcher.messages._history', [
                  'messages' => $messages,
                  'driver'   => $activeDriver,
                ])
              </ul>

              @if($messages->isEmpty())
                <div class="text-center py-6 text-body-secondary" id="emptyState">
                  <i class="icon-base ti tabler-messages icon-48px d-block mb-2"></i>
                  <p class="mb-0">Send the first message to {{ $activeDriver->name }}.</p>
                </div>
              @endif
            </div>

            <div class="chat-history-footer shadow-xs">
              <form class="form-send-message d-flex justify-content-between align-items-center"
                    method="POST" action="{{ route('dispatcher.messages.store', $activeDriver->id) }}">
                @csrf
                <label class="visually-hidden" for="message-input">Message</label>
                <input id="message-input" name="body" maxlength="2000" required autocomplete="off"
                       class="form-control message-input border-0 me-4 shadow-none"
                       placeholder="Type your message here..." />
                <div class="message-actions d-flex align-items-center">
                  <button type="submit" class="btn btn-primary d-flex send-msg-btn">
                    <span class="align-middle d-md-inline-block d-none">Send</span>
                    <i class="icon-base ti tabler-send icon-16px ms-md-2 ms-0"></i>
                  </button>
                </div>
              </form>
              <small class="text-danger d-block mt-1 {{ $errors->has('body') ? '' : 'd-none' }}" id="send-error">{{ $errors->first('body') }}</small>
            </div>
          </div>
        @endif
      </div>

      <div class="app-overlay"></div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const body = document.querySelector('.chat-history-body');

    // Vuexy scrolls the history with PerfectScrollbar; fall back to the
    // native scroll if the library did not load.
    let scroller = null;
    if (body) {
      if (window.PerfectScrollbar) {
        scroller = new PerfectScrollbar(body, { wheelPropagation: false, suppressScrollX: true });
      }
      body.scrollTop = body.scrollHeight;
    }

    function toBottom() {
      if (!body) return;
      if (scroller) scroller.update();
      body.scrollTop = body.scrollHeight;
    }
    toBottom();

    // Filter both lists from the one search box, matching the template's
    // "No Chats Found" / "No Drivers Found" placeholders.
    const search = document.querySelector('.chat-search-input');
    search?.addEventListener('input', function () {
      const term = search.value.trim().toLowerCase();

      [['#chat-list', '.chat-list-item-0'], ['#contact-list', '.contact-list-item-0']].forEach(function ([listSel, emptySel]) {
        const list = document.querySelector(listSel);
        if (!list) return;

        let shown = 0;
        list.querySelectorAll('.chat-contact-list-item[data-name]').forEach(function (item) {
          const match = item.dataset.name.includes(term);
          item.classList.toggle('d-none', !match);
          if (match) shown++;
        });

        list.querySelector(emptySel)?.classList.toggle('d-none', shown > 0);
      });
    });

    @if($activeDriver)
    const pollUrl = @json(route('dispatcher.messages.poll', $activeDriver->id));
    const avatar  = @json($activeDriver->avatar_url);
    let since     = @json(optional($messages->last()?->created_at)->toIso8601String());

    function bubble(m) {
      const li = document.createElement('li');
      li.className = 'chat-message' + (m.is_mine ? ' chat-message-right' : '');
      li.id = 'msg-' + m.id;

      const avatarHtml =
        '<div class="user-avatar flex-shrink-0 ' + (m.is_mine ? 'ms-4' : 'me-4') + '">' +
          '<div class="avatar avatar-sm"><img src="' + (m.is_mine ? @json(auth()->user()->avatar_url) : avatar) + '" alt="" class="rounded-circle" /></div>' +
        '</div>';

      const text = document.createElement('p');
      text.className = 'mb-0';
      text.textContent = m.body;

      const wrapper = document.createElement('div');
      wrapper.className = 'chat-message-wrapper flex-grow-1';
      const box = document.createElement('div');
      box.className = 'chat-message-text';
      if (m.time) box.title = m.time;
      box.appendChild(text);
      const meta = document.createElement('div');
      meta.className = 'chat-message-time ' + (m.is_mine ? 'text-end ' : '') + 'text-body-secondary mt-1';
      meta.innerHTML = (m.is_mine ? '<i class="icon-base ti tabler-check icon-16px me-1"></i>' : '') +
                       '<small>' + (m.time || '') + '</small>';
      wrapper.appendChild(box);
      wrapper.appendChild(meta);

      const row = document.createElement('div');
      row.className = 'd-flex overflow-hidden';
      row.innerHTML = m.is_mine ? '' : avatarHtml;
      row.appendChild(wrapper);
      if (m.is_mine) row.insertAdjacentHTML('beforeend', avatarHtml);

      li.appendChild(row);
      return li;
    }

    // The socket delivers in real time; this poll is the fallback so a
    // dropped connection never means a missed message.
    async function poll() {
      try {
        const response = await fetch(pollUrl + (since ? '?since=' + encodeURIComponent(since) : ''),
                                     { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;

        const json  = await response.json();
        const fresh = (json.data || []).filter(m => !document.getElementById('msg-' + m.id));
        if (fresh.length === 0) return;

        fresh.forEach(function (m) {
          appendMessage(m);
          since = m.created_at;
        });
        toBottom();
      } catch (error) {
        // A failed poll is not worth surfacing; the next one catches up.
      }
    }

    const list  = document.querySelector('.chat-history');
    const today = @json(now()->toDateString());

    /*
     * The one way a message reaches the bottom of the thread, whether it came
     * from the poll, the socket or our own send. It skips anything already on
     * the page, and opens a new day divider when the date changes.
     */
    function appendMessage(m) {
      if (document.getElementById('msg-' + m.id)) return;

      document.getElementById('emptyState')?.remove();

      const date     = (m.created_at || '').slice(0, 10);
      const dividers = list.querySelectorAll('.chat-date-divider');
      const lastDay  = dividers.length ? dividers[dividers.length - 1].dataset.date : null;

      if (date && date !== lastDay) {
        const divider = document.createElement('li');
        divider.className = 'chat-date-divider text-center';
        divider.dataset.date = date;
        divider.innerHTML = '<span class="badge bg-label-secondary"></span>';
        divider.firstChild.textContent = date === today ? 'Today' : date;
        list.appendChild(divider);
      }

      list.appendChild(bubble(m));
    }

    // ── Sending ────────────────────────────────────────
    // The form still posts normally without JavaScript; with it, the message
    // goes over fetch so the page (and its scroll position) stays put.
    const form      = document.querySelector('.form-send-message');
    const input     = document.getElementById('message-input');
    const sendBtn   = form.querySelector('.send-msg-btn');
    const sendError = document.getElementById('send-error');

    function showSendError(text) {
      sendError.textContent = text || '';
      sendError.classList.toggle('d-none', !text);
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      if (!input.value.trim() || sendBtn.disabled) return;

      sendBtn.disabled = true;
      showSendError('');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        const json = await response.json().catch(() => ({}));

        if (!response.ok) {
          showSendError(json.errors?.body?.[0] || json.message || 'Message could not be sent. Please try again.');
          return;
        }

        input.value = '';
        appendMessage(json.data);
        toBottom();
      } catch (error) {
        showSendError('Message could not be sent. Check your connection and try again.');
      } finally {
        sendBtn.disabled = false;
        input.focus();
      }
    });

    // ── Older messages ─────────────────────────────────
    const olderUrl  = @json(route('dispatcher.messages.older', $activeDriver->id));
    const olderBox  = document.getElementById('load-older');
    const olderBtn  = olderBox.querySelector('button');
    let oldestId    = @json($messages->first()?->id);
    let hasOlder    = @json($hasOlder);
    let loadingOlder = false;

    async function loadOlder() {
      if (!hasOlder || loadingOlder || !oldestId) return;

      loadingOlder = true;
      olderBtn.disabled = true;
      olderBtn.querySelector('.spinner-border').classList.remove('d-none');

      try {
        const response = await fetch(olderUrl + '?before=' + oldestId, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;

        const json = await response.json();
        const page = document.createElement('template');
        page.innerHTML = json.html.trim();

        // Two pages that meet inside one day would each open it with a divider.
        const firstDivider = list.firstElementChild;
        const newDividers  = page.content.querySelectorAll('.chat-date-divider');
        const lastNewDay   = newDividers.length ? newDividers[newDividers.length - 1].dataset.date : null;
        if (firstDivider?.classList.contains('chat-date-divider') && firstDivider.dataset.date === lastNewDay) {
          firstDivider.remove();
        }

        // Keep the message the dispatcher was reading where it was, rather
        // than letting the new rows push it down.
        const fromBottom = body.scrollHeight - body.scrollTop;
        list.prepend(page.content);
        body.scrollTop = body.scrollHeight - fromBottom;
        if (scroller) scroller.update();

        oldestId = json.oldest_id ?? oldestId;
        hasOlder = json.has_more;
        olderBox.classList.toggle('d-none', !hasOlder);
      } catch (error) {
        // Left as it was; scrolling up again or the button retries.
      } finally {
        loadingOlder = false;
        olderBtn.disabled = false;
        olderBtn.querySelector('.spinner-border').classList.add('d-none');
      }
    }

    olderBtn.addEventListener('click', loadOlder);
    body.addEventListener('scroll', function () {
      if (body.scrollTop < 80) loadOlder();
    });

    /*
     * Polling is the floor, not the mechanism. The socket simply calls poll()
     * the moment something arrives, so messages render through exactly the
     * one path they always did — and poll() already skips anything whose
     * bubble is on the page, so an event and a poll landing together cannot
     * show the message twice.
     *
     * With the socket up the interval only has to cover a missed event, so it
     * drops to a slow safety net.
     */
    const FAST_POLL = 5000;
    const SLOW_POLL = 30000;
    let pollTimer = setInterval(poll, FAST_POLL);

    function repoll(every) {
      clearInterval(pollTimer);
      pollTimer = setInterval(poll, every);
    }

    window.addEventListener('rr:connected', function () { repoll(SLOW_POLL); });
    window.addEventListener('rr:disconnected', function () { repoll(FAST_POLL); poll(); });
    window.addEventListener('rr:message:new', function () { poll(); });
    @endif
  });
</script>
@endsection
