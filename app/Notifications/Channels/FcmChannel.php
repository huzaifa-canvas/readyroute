<?php

namespace App\Notifications\Channels;

use App\Services\DeadDeviceTokenException;
use App\Services\FcmClient;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification to the driver's device through Firebase.
 *
 * This channel is added to via() only when push is enabled in config and the
 * driver has left push notifications on, so enabling push is a configuration
 * change: the notification classes already carry their FCM payloads.
 */
class FcmChannel
{
    public function __construct(private readonly FcmClient $fcm)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $token = $notifiable->device_token ?? null;

        if (! $token || ! method_exists($notification, 'toFcm')) {
            return;
        }

        try {
            $this->fcm->send($token, $notification->toFcm($notifiable));
        } catch (DeadDeviceTokenException) {
            // The app is gone from that device. Drop the token so we stop
            // sending to it and so the driver's next sign-in registers a fresh
            // one.
            $notifiable->forceFill(['device_token' => null])->saveQuietly();
        }
    }
}
