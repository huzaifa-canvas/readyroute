<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Resources\Driver\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\SocketEmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dispatch chat.
 *
 * Sending goes over HTTP rather than the socket so validation, tenant scoping
 * and the database write all stay in one place. The socket only delivers, which
 * means a socket outage delays a message but never loses one.
 */
class MessageController extends BaseDriverController
{
    public function __construct(private readonly SocketEmitter $socket)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'since'    => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $companyId = $this->companyId();

        if (! $companyId) {
            return $this->fail('You are not linked to a dispatch company yet.', 422);
        }

        $query = Message::thread($companyId, $this->driver()->id)
            ->with(['sender']);

        // "since" lets the app top up an open conversation instead of
        // re-fetching the whole thread.
        if ($request->filled('since')) {
            $messages = $query
                ->where('created_at', '>', Carbon::parse($request->input('since')))
                ->orderBy('created_at')
                ->limit(200)
                ->get();

            return $this->ok([
                'messages' => MessageResource::collection($messages),
                'dispatcher' => $this->dispatcherSummary(),
            ]);
        }

        $messages = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page') ?: 30);

        // Oldest first within the page, which is the order a chat reads in.
        $ordered = $messages->getCollection()->sortBy('created_at')->values();

        return response()->json([
            'status'  => true,
            'message' => null,
            'data'    => [
                'messages'   => MessageResource::collection($ordered)->resolve($request),
                'dispatcher' => $this->dispatcherSummary(),
            ],
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
                'has_more'     => $messages->hasMorePages(),
                'unread_count' => Message::unreadFor($this->driver()->id)->count(),
            ],
            'errors' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $driver    = $this->driver();
        $companyId = $this->companyId();

        if (! $companyId) {
            return $this->fail('You are not linked to a dispatch company yet.', 422);
        }

        $message = Message::create([
            'dispatcher_id' => $companyId,
            'driver_id'     => $driver->id,
            'sender_id'     => $driver->id,
            'receiver_id'   => $companyId,
            'body'          => $request->input('body'),
        ]);

        $message->load('sender');

        $payload = (new MessageResource($message))->resolve($request);

        // Deliver to everyone watching the thread, and raise the dispatcher's
        // badge even if they have the thread closed.
        $this->socket->queue(
            SocketEmitter::threadRoom($companyId, $driver->id),
            'message:new',
            $payload
        );

        $this->socket->queue(
            SocketEmitter::userRoom($companyId),
            'message:new',
            $payload
        );

        if ($dispatcher = User::find($companyId)) {
            $dispatcher->notify(new NewMessageNotification($message));
        }

        return $this->created(new MessageResource($message), 'Message sent.');
    }

    /**
     * Mark everything the dispatcher sent as read, which is what turns their
     * single tick into a double tick.
     */
    public function markRead(): JsonResponse
    {
        $driver    = $this->driver();
        $companyId = $this->companyId();

        if (! $companyId) {
            return $this->fail('You are not linked to a dispatch company yet.', 422);
        }

        $ids = Message::thread($companyId, $driver->id)
            ->unreadFor($driver->id)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Message::whereIn('id', $ids)->update(['read_at' => now()]);

            $this->socket->queue(
                SocketEmitter::threadRoom($companyId, $driver->id),
                'message:read',
                ['message_ids' => $ids->all(), 'reader_id' => $driver->id]
            );
        }

        return $this->ok([
            'marked'       => $ids->count(),
            'unread_count' => 0,
        ], 'Messages marked as read.');
    }

    private function dispatcherSummary(): ?array
    {
        $dispatcher = $this->driver()->dispatcher;

        if (! $dispatcher) {
            return null;
        }

        return [
            'id'         => $dispatcher->id,
            'name'       => $dispatcher->name,
            'avatar_url' => $dispatcher->avatar_url,
        ];
    }
}
