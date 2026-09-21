<?php

return [

    /*
    |--------------------------------------------------------------------------
    | On-Time Performance
    |--------------------------------------------------------------------------
    |
    | A trip counts as on-time when the driver's "arrived at pickup" timestamp
    | falls no later than the scheduled pickup time plus this grace period.
    | Arriving early is always on-time. Cancelled trips are excluded from the
    | rate entirely so they neither help nor hurt a driver's percentage.
    |
    */

    'on_time_grace_minutes' => env('READYROUTE_ON_TIME_GRACE', 10),

    /*
    |--------------------------------------------------------------------------
    | Distance & ETA Provider
    |--------------------------------------------------------------------------
    |
    | "haversine" needs no API key and returns straight-line distance, which is
    | what we run on until a Google Directions key is available. Switching to
    | "google" swaps the implementation without touching any calling code.
    |
    */

    'distance' => [
        'provider' => env('READYROUTE_DISTANCE_PROVIDER', 'haversine'),

        // Straight-line distance understates road distance. This multiplier
        // brings the haversine estimate closer to a realistic driving figure.
        'road_factor' => env('READYROUTE_ROAD_FACTOR', 1.30),

        // Average miles per hour used to turn a distance into a rough ETA.
        'average_speed_mph' => env('READYROUTE_AVG_SPEED_MPH', 28),

        'google_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signatures
    |--------------------------------------------------------------------------
    |
    | Driver e-signatures are patient-linked medical transport records, so they
    | are written to a private disk and only ever handed out as a short-lived
    | signed URL — never a public path.
    |
    */

    'signatures' => [
        'disk' => env('READYROUTE_SIGNATURE_DISK', 'local'),
        'path' => 'signatures',
        'url_ttl_minutes' => 15,
        'max_kb' => 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notifications
    |--------------------------------------------------------------------------
    |
    | Notifications are written to the database from day one. Turning this on
    | additionally delivers them through Firebase, for drivers who have left
    | push enabled in the app. Every notification already carries an FCM
    | payload, so enabling push needs no change to the notification classes.
    |
    */

    'push' => [
        'enabled' => env('READYROUTE_PUSH_ENABLED', false),

        'firebase' => [
            'project_id'       => env('FIREBASE_PROJECT_ID'),
            'credentials_path' => env('FIREBASE_CREDENTIALS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Location Tracking
    |--------------------------------------------------------------------------
    |
    | A driver is treated as offline once no ping or socket activity has been
    | seen for this many minutes.
    |
    */

    'presence' => [
        'offline_after_minutes' => env('READYROUTE_OFFLINE_AFTER', 5),
    ],

];
