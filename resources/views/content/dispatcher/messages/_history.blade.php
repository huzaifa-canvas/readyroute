{{--
  A run of messages with their day dividers. Shared by the first render and the
  "older messages" endpoint, so a page loaded on scroll looks exactly like the
  one the server drew. Each divider carries its date so the page can drop a
  duplicate where two batches meet on the same day.
--}}
@php($lastDate = null)

@foreach($messages as $message)
  @if($message->created_at->toDateString() !== $lastDate)
    <li class="chat-date-divider text-center" data-date="{{ $message->created_at->toDateString() }}">
      <span class="badge bg-label-secondary">
        {{ $message->created_at->isToday() ? 'Today'
           : ($message->created_at->isYesterday() ? 'Yesterday'
           : $message->created_at->format('d M Y')) }}
      </span>
    </li>
    @php($lastDate = $message->created_at->toDateString())
  @endif

  @include('content.dispatcher.messages._bubble', [
    'message' => $message,
    'driver'  => $driver,
  ])
@endforeach
