<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\SocketEmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The dispatcher's side of the driver chat.
 *
 * Same Message rows, same thread scope and same socket rooms as the web panel
 * and the driver app — only the keyboard is different. A message typed here
 * reaches the driver through the socket and renders identically to one they
 * sent themselves, because both sides emit the same payload shape.
 *
 * Chat is deliberately not written to the notifications table; the unread
 * badge is counted from the messages themselves.
 */
class MessageController extends BaseDispatcherController
{
    private const PAGE_SIZE = 30;

    public function __construct(private readonly SocketEmitter $socket)
    {
    }

    /**
     * The conversation list: one row per driver, most recently active first.
     */
    public function index(): JsonResponse
    {
        $companyId = $this->companyId();

        $drivers = $this->drivers()->orderBy('name')->get();

        // One query for the whole board rather than two per driver.
        $lastMessages = Message::where('dispatcher_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('driver_id');

        $unread = Message::where('dispatcher_id', $companyId)
            ->where('receiver_id', $companyId)
            ->whereNull('read_at')
            ->selectRaw('driver_id, COUNT(*) as total')
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        $conversations = $drivers->map(function (User $driver) use ($lastMessages, $unread) {
            $last = $lastMessages->get($driver->id)?->first();

            return [
                'driver' => [
                    'id'         => $driver->id,
                    'name'       => $driver->name,
                    'avatar_url' => $driver->avatar_url,
                    'is_online'  => $driver->isCurrentlyOnline(),
                ],
                'last_message' => $last ? [
                    'body'    => $last->body,
                    'at'      => optional($last->created_at)->toIso8601String(),
                    'time'    => optional($last->created_at)->format('g:i A'),
                    'is_mine' => $last->sender_id !== $driver->id,
                ] : null,
                'last_at' => optional($last?->created_at)->toIso8601String(),
                'unread'  => (int) ($unread[$driver->id] ?? 0),
            ];
        })
        // Drivers who have never messaged sink to the bottom but stay listed,
        // so a new conversation can be started with them.
        ->sortByDesc(fn ($row) => $row['last_at'] ?? '')
        ->values();

        return $this->ok([
            'conversations' => $conversations,
            'total_unread'  => (int) $unread->sum(),
        ]);
    }

    /**
     * One thread. Opening it marks the driver's messages read, which is what
     * turns their single tick into a double tick.
     */
    public function thread(Request $request, int $driverId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $companyId = $this->companyId();

        // Marked before the rows are read, so the response carries the read
        // state it just set rather than the one it replaced.
        $this->markThreadRead($companyId, $driver);

        $before = $request->integer('before') ?: null;

        $rows = Message::thread($companyId, $driver->id)
            ->with('sender')
            ->when($before, fn ($query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        // The extra row only says whether another page exists.
        $hasOlder = $rows->count() > self::PAGE_SIZE;
        $messages = $rows->take(self::PAGE_SIZE)->reverse()->values();

        return $this->ok([
            'driver' => [
                'id'         => $driver->id,
                'name'       => $driver->name,
                'avatar_url' => $driver->avatar_url,
                'is_online'  => $driver->isCurrentlyOnline(),
                'phone'      => $driver->phone_number,
            ],
            'messages'  => $messages->map(fn (Message $m) => $this->payload($m, $this->user()->id))->values(),
            'oldest_id' => $messages->first()?->id,
            'has_older' => $hasOlder,
        ]);
    }

    public function store(Request $request, int $driverId): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $companyId = $this->companyId();

        $message = Message::create([
            'dispatcher_id' => $companyId,
            'driver_id'     => $driver->id,
            // The signed-in user may be panel staff, so the sender is them
            // while the thread still belongs to the company.
            'sender_id'   => $this->user()->id,
            'receiver_id' => $driver->id,
            'body'        => $request->input('body'),
        ]);

        $message->load('sender');

        // is_mine is viewer-dependent, so each side gets its own copy.
        $forDriver     = $this->payload($message, $driver->id);
        $forDispatcher = $this->payload($message, $this->user()->id);

        // The driver joins their thread room on every connection, so this one
        // emit reaches them.
        $this->socket->queue(SocketEmitter::threadRoom($companyId, $driver->id), 'message:new', $forDriver);

        // Everyone in the office, so a second dispatcher sees it too.
        $this->socket->queue(SocketEmitter::dispatcherRoom($companyId), 'message:new', $forDispatcher);

        $driver->notify(new NewMessageNotification($message));

        return $this->created($forDispatcher);
    }

    /**
     * Mark a thread read without opening it.
     */
    public function markRead(int $driverId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $this->markThreadRead($this->companyId(), $driver);

        return $this->ok(null, 'Thread marked as read.');
    }

    /**
     * Polling fallback for an open thread.
     *
     * The socket delivers in real time; this is what keeps a dropped socket
     * from meaning a missed message.
     */
    public function poll(Request $request, int $driverId): JsonResponse
    {
        $driver = $this->drivers()->find($driverId);

        if (! $driver) {
            return $this->notFound('Driver not found.');
        }

        $companyId = $this->companyId();

        $this->markThreadRead($companyId, $driver);

        $query = Message::thread($companyId, $driver->id)->with('sender');

        if ($request->filled('since')) {
            $query->where('created_at', '>', Carbon::parse($request->input('since')));
        }

        $messages = $query->orderBy('created_at')->limit(100)->get();

        return $this->ok([
            'messages'    => $messages->map(fn (Message $m) => $this->payload($m, $this->user()->id))->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    // ── Shared pieces ────────────────────────────────────────────────

    /**
     * One message in the shape both apps already parse.
     *
     * @return array<string, mixed>
     */
    private function payload(Message $message, int $viewerId): array
    {
        return [
            'id'      => $message->id,
            'body'    => $message->body,
            'is_mine' => $message->sender_id === $viewerId,
            'sender'  => [
                'id'         => $message->sender_id,
                'name'       => $message->sender?->name,
                'avatar_url' => $message->sender?->avatar_url,
            ],
            'is_read'    => $message->isRead(),
            'read_at'    => optional($message->read_at)->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
            'time'       => $message->created_at->format('g:i A'),
            'driver_id'  => $message->driver_id,
        ];
    }

    /**
     * Mark everything this driver sent as read, and tell the thread so their
     * app can update its ticks.
     */
    private function markThreadRead(int $companyId, User $driver): void
    {
        $ids = Message::thread($companyId, $driver->id)
            ->where('sender_id', $driver->id)
            ->whereNull('read_at')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        Message::whereIn('id', $ids)->update(['read_at' => now()]);

        $this->socket->queue(
            SocketEmitter::threadRoom($companyId, $driver->id),
            'message:read',
            ['message_ids' => $ids->all(), 'reader_id' => $this->user()->id]
        );
    }
}
