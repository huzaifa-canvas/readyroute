{{--
  One message bubble, in Vuexy's chat-message shape.

  Anything the driver did not send is ours, so it sits on the right — that
  includes messages typed by another panel user, whose name is then shown.
--}}
@php($mine = $message->sender_id !== $driver->id)

<li id="msg-{{ $message->id }}" class="chat-message {{ $mine ? 'chat-message-right' : '' }}">
  <div class="d-flex overflow-hidden">

    @unless($mine)
      <div class="user-avatar flex-shrink-0 me-4">
        <div class="avatar avatar-sm">
          <img src="{{ $driver->avatar_url }}" alt="{{ $driver->name }}" class="rounded-circle" />
        </div>
      </div>
    @endunless

    <div class="chat-message-wrapper flex-grow-1">
      <div class="chat-message-text">
        <p class="mb-0">{{ $message->body }}</p>
      </div>

      <div class="{{ $mine ? 'text-end ' : '' }}text-body-secondary mt-1">
        @if($mine)
          {{-- Sent vs read, the same single/double tick the driver app shows --}}
          <i class="icon-base ti {{ $message->isRead() ? 'tabler-checks text-success' : 'tabler-check' }} icon-16px me-1"
             title="{{ $message->isRead() ? 'Read' : 'Sent' }}"></i>
        @endif

        @if($mine && $message->sender_id !== auth()->id())
          <small>{{ $message->sender?->name }} &middot;</small>
        @endif

        <small>{{ $message->created_at->format('g:i A') }}</small>
      </div>
    </div>

    @if($mine)
      <div class="user-avatar flex-shrink-0 ms-4">
        <div class="avatar avatar-sm">
          <img src="{{ $message->sender?->avatar_url ?? auth()->user()->avatar_url }}" alt="" class="rounded-circle" />
        </div>
      </div>
    @endif
  </div>
</li>
