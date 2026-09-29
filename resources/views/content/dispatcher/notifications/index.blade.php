@extends('layouts/layoutMaster')

@section('title', 'Notification Center')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @include('content.admin._partials.flash')

  @php
    // Icon and colour per notification kind, so the list is scannable without
    // reading every line.
    $icons = [
      'sos'          => ['tabler-urgent',          'danger'],
      'incident'     => ['tabler-alert-triangle',  'warning'],
      'new_message'  => ['tabler-message-circle',  'info'],
      'trip_added'   => ['tabler-calendar-plus',   'primary'],
      'route_change' => ['tabler-route-2',         'warning'],
      'trip_cancelled' => ['tabler-calendar-x',    'danger'],
      'reminder'     => ['tabler-bell-ringing',    'secondary'],
    ];
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="fw-bold text-heading mb-1">Notification Center</h4>
      <p class="text-muted mb-0 small">Everything the system has told you.</p>
    </div>
    @if($counts['unread'] > 0)
      <form method="POST" action="{{ route('dispatcher.notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn btn-label-primary">
          <i class="ti tabler-checks me-1"></i>Mark all read ({{ $counts['unread'] }})
        </button>
      </form>
    @endif
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('dispatcher.notifications.index') }}"
           class="btn btn-sm {{ ! request('filter') && ! request('kind') ? 'btn-primary' : 'btn-label-secondary' }}">
          All <span class="badge bg-white text-dark ms-1">{{ $counts['all'] }}</span>
        </a>
        <a href="{{ route('dispatcher.notifications.index', ['filter' => 'unread']) }}"
           class="btn btn-sm {{ request('filter') === 'unread' ? 'btn-primary' : 'btn-label-secondary' }}">
          Unread <span class="badge bg-white text-dark ms-1">{{ $counts['unread'] }}</span>
        </a>
      </div>

      <div class="d-flex flex-wrap gap-2">
        @foreach($kinds as $key => $label)
          <a href="{{ route('dispatcher.notifications.index', array_filter(['kind' => $key, 'filter' => request('filter')])) }}"
             class="btn btn-sm {{ request('kind') === $key ? 'btn-label-primary' : 'btn-text-secondary' }}">
            {{ $label }}
          </a>
        @endforeach
        @if(request('kind'))
          <a href="{{ route('dispatcher.notifications.index', array_filter(['filter' => request('filter')])) }}"
             class="btn btn-sm btn-text-secondary">
            <i class="ti tabler-x icon-xs me-1"></i>Clear
          </a>
        @endif
      </div>
    </div>
  </div>

  <div class="card">
    <div class="list-group list-group-flush">
      @forelse($notifications as $notification)
        @php($kind = $notification->data['kind'] ?? 'general')
        @php($icon = $icons[$kind] ?? ['tabler-bell', 'secondary'])
        @php($unread = $notification->read_at === null)

        <div class="list-group-item d-flex gap-3 py-3 {{ $unread ? 'bg-lighter' : '' }}">
          <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded bg-label-{{ $icon[1] }}">
              <i class="ti {{ $icon[0] }}"></i>
            </span>
          </div>

          <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
              <span class="fw-semibold text-break">
                {{ $notification->data['title'] ?? 'Notification' }}
                @if($unread)
                  <span class="badge badge-dot bg-primary ms-1" title="Unread"></span>
                @endif
              </span>
              <small class="text-muted flex-shrink-0" title="{{ $notification->created_at->format('d M Y H:i') }}">
                {{ $notification->created_at->diffForHumans() }}
              </small>
            </div>

            <p class="mb-2 text-muted small text-break">{{ $notification->data['body'] ?? '' }}</p>

            <div class="d-flex flex-wrap gap-3">
              @php($data = $notification->data['data'] ?? [])

              @if(! empty($data['incident_id']))
                <a href="{{ route('dispatcher.incidents.show', $data['incident_id']) }}" class="btn btn-text-primary btn-sm p-0">
                  <i class="ti tabler-arrow-right icon-xs me-1"></i>Open incident
                </a>
              @elseif(! empty($data['trip_id']))
                <a href="{{ route('dispatcher.trip.details', $data['trip_id']) }}" class="btn btn-text-primary btn-sm p-0">
                  <i class="ti tabler-arrow-right icon-xs me-1"></i>Open trip
                </a>
              @elseif(! empty($data['driver_id']))
                <a href="{{ route('dispatcher.messages.thread', $data['driver_id']) }}" class="btn btn-text-primary btn-sm p-0">
                  <i class="ti tabler-arrow-right icon-xs me-1"></i>Open conversation
                </a>
              @endif

              @if($unread)
                <form method="POST" action="{{ route('dispatcher.notifications.read', $notification->id) }}">
                  @csrf
                  <button type="submit" class="btn btn-text-secondary btn-sm p-0">
                    <i class="ti tabler-check icon-xs me-1"></i>Mark read
                  </button>
                </form>
              @endif

              <form method="POST" action="{{ route('dispatcher.notifications.delete', $notification->id) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-text-danger btn-sm p-0">
                  <i class="ti tabler-trash icon-xs me-1"></i>Remove
                </button>
              </form>
            </div>
          </div>
        </div>
      @empty
        <div class="list-group-item text-center py-5 text-muted">
          <i class="ti tabler-bell-off fs-1 d-block mb-2 text-secondary"></i>
          <h6>Nothing here</h6>
          <p class="mb-0 small">
            @if(request('filter') || request('kind'))
              No notifications match this filter.
            @else
              Notifications appear here as drivers report incidents, send messages and complete trips.
            @endif
          </p>
        </div>
      @endforelse
    </div>

    @if($notifications->hasPages())
    <div class="card-footer d-flex justify-content-center py-3 border-top">
      {{ $notifications->links() }}
    </div>
    @endif
  </div>
</div>
@endsection
