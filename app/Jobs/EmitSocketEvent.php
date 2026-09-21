<?php

namespace App\Jobs;

use App\Services\SocketEmitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers one socket event off the request path.
 *
 * Requires a queue worker to be running. If none is, realtime delivery stops
 * while the REST API carries on unaffected, which is the intended failure mode.
 */
class EmitSocketEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public string $room,
        public string $event,
        public array $payload = [],
    ) {
    }

    public function handle(SocketEmitter $emitter): void
    {
        $emitter->emit($this->room, $this->event, $this->payload);
    }
}
