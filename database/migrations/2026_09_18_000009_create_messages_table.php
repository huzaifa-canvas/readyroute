<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispatch chat. A thread is simply the pair of a dispatcher company and
     * one of its drivers, so no separate threads table is needed.
     *
     * Messages are written here over HTTP and then pushed out by the socket
     * server, which means a socket outage delays delivery but never loses a
     * message.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // The company the thread belongs to, denormalised so a thread can
            // be loaded with one indexed lookup.
            $table->foreignId('dispatcher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();

            $table->text('body');

            // Drives the double tick in the chat screen.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['dispatcher_id', 'driver_id', 'created_at'], 'messages_thread_index');
            $table->index(['receiver_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
