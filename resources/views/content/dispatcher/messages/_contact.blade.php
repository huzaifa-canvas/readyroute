{{--
  One row in the chat sidebar, in Vuexy's chat-contact-list-item shape.

  $row          conversation summary (driver, last_message, last_at, unread)
  $active       whether this is the open conversation
  $showPreview  true in the "Chats" list, false in the plain driver list
--}}
@php($driver = $row->driver)

<li class="chat-contact-list-item {{ $active ? 'active' : '' }} mb-1"
    data-name="{{ strtolower($driver->name . ' ' . $driver->driver_code) }}">
  <a class="d-flex align-items-center" href="{{ route('dispatcher.messages.index', ['driver' => $driver->id]) }}">
    <div class="flex-shrink-0 avatar {{ $driver->isCurrentlyOnline() ? 'avatar-online' : 'avatar-offline' }}">
      <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
    </div>

    <div class="chat-contact-info flex-grow-1 ms-4">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="chat-contact-name text-truncate m-0 fw-normal">{{ $driver->name }}</h6>

        @if($showPreview && $row->last_at)
          <small class="chat-contact-list-item-time">{{ $row->last_at->diffForHumans(null, true) }}</small>
        @endif
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <small class="chat-contact-status text-truncate">
          @if($showPreview && $row->last_message)
            {{-- A tick marks the ones we sent, as in the template --}}
            @if($row->last_message->sender_id !== $driver->id)
              <i class="icon-base ti tabler-check icon-12px me-1"></i>
            @endif
            {{ \Illuminate\Support\Str::limit($row->last_message->body, 34) }}
          @else
            {{ $driver->driver_code }}
          @endif
        </small>

        {{-- Always rendered, hidden at zero, so the navbar poller can
             update the count without rebuilding the row. --}}
        <span class="badge bg-danger rounded-pill flex-shrink-0 ms-1 {{ $row->unread > 0 ? '' : 'd-none' }}"
              data-unread-driver="{{ $driver->id }}">{{ $row->unread }}</span>
      </div>
    </div>
  </a>
</li>
