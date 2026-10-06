<?php

namespace App\Http\Controllers\Web\Dispatcher;

use App\Http\Controllers\Controller;
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
 * The driver API already wrote every message through HTTP and let the socket
 * server deliver it; this is the same arrangement in reverse, reusing the same
 * Message rows, thread scope and rooms. Nothing about the storage changes —
 * only who is holding the keyboard.
 */
class MessageController extends Controller
{
    /**
     * Messages per page. The open thread starts with the newest page and
     * earlier ones are fetched as the dispatcher scrolls up.
     */
    private const PAGE_SIZE = 30;

    public function __construct(private readonly SocketEmitter $socket)
    {
    }

    /**
     * Conversation list: one row per driver, most recently active first.
     */
    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $drivers = User::driversOf($companyId)->orderBy('name')->get();

        // One query for the whole board rather than two per driver.
        $lastMessages = Message::where('dispatcher_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('driver_id');

        $unreadCounts = Message::where('dispatcher_id', $companyId)
            ->where('receiver_id', $companyId)
            ->whereNull('read_at')
            ->selectRaw('driver_id, COUNT(*) as total')
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        $conversations = $drivers->map(function (User $driver) use ($lastMessages, $unreadCounts) {
            $last = $lastMessages->get($driver->id)?->first();

            return (object) [
                'driver'       => $driver,
                'last_message' => $last,
                'last_at'      => $last?->created_at,
                'unread'       => (int) ($unreadCounts[$driver->id] ?? 0),
            ];
        })
        // Drivers who have never messaged sink to the bottom, but stay
        // listed so a dispatcher can open a new conversation with them.
        ->sortByDesc(fn ($row) => $row->last_at?->timestamp ?? 0)
        ->values();

        // Vuexy's chat is one page: the list and the open conversation live
        // side by side, so the selected driver comes in as a query parameter
        // rather than its own route.
        $activeDriver = $request->filled('driver')
            ? $drivers->firstWhere('id', (int) $request->input('driver'))
            : null;

        $messages = collect();
        $hasOlder = false;

        if ($activeDriver) {
            [$messages, $hasOlder] = $this->page($companyId, $activeDriver);

            $this->markThreadRead($companyId, $activeDriver);

            // Opening the thread clears its badge, so the sidebar must not
            // still be showing the count this request just consumed.
            $conversations = $conversations->map(function ($row) use ($activeDriver) {
                if ($row->driver->id === $activeDriver->id) {
                    $row->unread = 0;
                }

                return $row;
            });
        }

        $totalUnread = $conversations->sum('unread');

        return view('content.dispatcher.messages.index', compact(
            'conversations', 'activeDriver', 'messages', 'totalUnread', 'hasOlder'
        ));
    }

    /**
     * The page of messages before a given id, rendered with the same partials
     * as the first load so the browser only has to prepend it.
     */
    public function older(Request $request, $driverId): JsonResponse
    {
        $request->validate([
            'before' => ['required', 'integer', 'min:1'],
        ]);

        $driver = $this->findDriver($driverId);

        [$messages, $hasOlder] = $this->page(
            auth()->user()->companyId(),
            $driver,
            (int) $request->input('before')
        );

        return response()->json([
            'status'    => true,
            'html'      => view('content.dispatcher.messages._history', [
                'messages' => $messages,
                'driver'   => $driver,
            ])->render(),
            'oldest_id' => $messages->first()?->id,
            'has_more'  => $hasOlder,
        ]);
    }

    /**
     * One page of a thread, oldest first, ending just before $beforeId (or at
     * the newest message). Paged by id rather than created_at, since two
     * messages can share a second but never an id.
     *
     * @return array{0: \Illuminate\Support\Collection<int, Message>, 1: bool}
     */
    private function page(int $companyId, User $driver, ?int $beforeId = null): array
    {
        $rows = Message::thread($companyId, $driver->id)
            ->with('sender')
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        // The extra row only says whether another page exists.
        $hasOlder = $rows->count() > self::PAGE_SIZE;

        return [$rows->take(self::PAGE_SIZE)->reverse()->values(), $hasOlder];
    }

    /**
     * One conversation. Opening it marks the driver's messages as read, which
     * is what turns their single tick into a double tick.
     */
    /**
     * Kept so existing links and bookmarks still work; the conversation is
     * rendered by index() now.
     */
    public function thread(Request $request, $driverId)
    {
        $driver = $this->findDriver($driverId);

        return redirect()->route('dispatcher.messages.index', ['driver' => $driver->id]);
    }

    public function store(Request $request, $driverId)
    {
        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $companyId = auth()->user()->companyId();
        $driver    = $this->findDriver($driverId);

        $message = Message::create([
            'dispatcher_id' => $companyId,
            'driver_id'     => $driver->id,
            // The signed-in user may be staff, so the sender is them while the
            // thread still belongs to the company.
            'sender_id'     => auth()->id(),
            'receiver_id'   => $driver->id,
            'body'          => $request->input('body'),
        ]);

        $message->load('sender');

        // The driver app parses message:new with the shape its own API
        // returns, so the payload must match MessageResource exactly —
        // otherwise a message typed here renders differently from one the
        // driver sent themselves. is_mine is viewer-dependent, so each side
        // gets its own copy.
        $forDriver     = $this->payload($message, viewerId: $driver->id);
        $forDispatcher = $this->payload($message, viewerId: auth()->id());

        $this->socket->queue(SocketEmitter::threadRoom($companyId, $driver->id), 'message:new', $forDriver);
        $this->socket->queue(SocketEmitter::userRoom($driver->id), 'message:new', $forDriver);

        // Everyone in the office, so a second dispatcher sees the reply too.
        $this->socket->queue(SocketEmitter::dispatcherRoom($companyId), 'message:new', $forDispatcher);

        $driver->notify(new NewMessageNotification($message));

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $forDispatcher]);
        }

        return redirect()->route('dispatcher.messages.index', ['driver' => $driver->id]);
    }

    /**
     * One message in the same shape the driver API returns, so both sides of
     * the conversation speak one format.
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

            // Extra context the dispatcher board uses to route the event to
            // the right conversation row; the driver app ignores it.
            'driver_id'  => $message->driver_id,
        ];
    }

    /**
     * Poll endpoint for the open thread. The socket delivers in real time;
     * this is the fallback so a dropped socket never means a missed message.
     */
    public function poll(Request $request, $driverId): JsonResponse
    {
        $companyId = auth()->user()->companyId();
        $driver    = $this->findDriver($driverId);

        // Marked before the rows are read, so the response carries the read
        // state it just set rather than the one it replaced.
        $this->markThreadRead($companyId, $driver);

        $query = Message::thread($companyId, $driver->id)->with('sender');

        if ($request->filled('since')) {
            $query->where('created_at', '>', Carbon::parse($request->input('since')));
        }

        $messages = $query->orderBy('created_at')->limit(100)->get();

        return response()->json([
            'status' => true,
            'data'   => $messages->map(fn (Message $message) => [
                'id'         => $message->id,
                'body'       => $message->body,
                'sender_id'  => $message->sender_id,
                'is_mine'    => $message->sender_id !== $driver->id,
                'sender'     => ['id' => $message->sender_id, 'name' => $message->sender?->name],
                'created_at' => $message->created_at->toIso8601String(),
                'time'       => $message->created_at->format('g:i A'),
                'read_at'    => $message->read_at?->toIso8601String(),
            ])->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Mark everything this driver sent as read, and tell the thread so the
     * driver's app can update its ticks.
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
            ['message_ids' => $ids->all(), 'reader_id' => auth()->id()]
        );
    }

    /**
     * Drivers are always reached through the signed-in user's company, so a
     * mistyped id cannot open another tenant's conversation.
     */
    private function findDriver($driverId): User
    {
        return User::driversOf(auth()->user()->companyId())->findOrFail($driverId);
    }
}
