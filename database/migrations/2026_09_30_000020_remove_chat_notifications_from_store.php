<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clears the chat notifications already sitting in the notification list.
 *
 * Chat no longer writes to that table at all — see
 * NewMessageNotification::storesInDatabase() — but the rows written before
 * that change would otherwise stay in the bell for weeks, which is exactly the
 * noise the change exists to remove.
 *
 * Nothing is lost. Each of these rows was only ever a copy of a row in the
 * messages table, which is untouched: every message is still in the
 * conversation, still counted by the unread badge on the Messages icon.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('data', 'like', '%"kind":"new_message"%')
            ->delete();
    }

    public function down(): void
    {
        // Deliberately irreversible: these rows were duplicates of the
        // messages table, so there is nothing to restore them from, and
        // nothing that needs restoring.
    }
};
