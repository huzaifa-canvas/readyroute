<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Socket Server
    |--------------------------------------------------------------------------
    |
    | The socket.io server runs as its own Node process alongside the app. It
    | only ever delivers messages; nothing is written to the database through
    | it, so the app keeps working when it is down — realtime delivery simply
    | falls back to the client fetching over REST.
    |
    */

    'enabled' => env('SOCKET_ENABLED', true),

    // Where Laravel reaches the socket server to push an event out. This is a
    // server-to-server call, so it stays on localhost even in production where
    // clients connect through the public nginx address.
    'url' => env('SOCKET_URL', 'http://127.0.0.1:3001'),

    /*
     * Where a browser reaches the socket server. Different from the line
     * above: that one is localhost, this one has to be publicly reachable.
     *
     * Left empty on purpose. The dispatcher panel only attempts a socket when
     * this is set, and falls back to polling otherwise — so nothing breaks
     * before the public address exists, and a wrong value cannot take the
     * panel down with it.
     *
     * On an https panel this must be https/wss, or the browser blocks it as
     * mixed content.
     */
    'public_url' => env('SOCKET_PUBLIC_URL'),

    // Shared secret for both directions: Laravel proving itself when it emits,
    // and the socket server proving itself when it verifies a token.
    'secret' => env('SOCKET_SECRET'),

    // Kept short. A slow socket server must never hold up an API response.
    'timeout' => env('SOCKET_TIMEOUT', 3),

];
