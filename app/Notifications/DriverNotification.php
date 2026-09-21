<?php

namespace App\Notifications;

use App\Support\DriverPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base for everything the driver app shows in its notification list.
 *
 * Channels are decided from the driver's own preference, so the Push
 * Notifications toggle on the settings screen controls real behaviour the
 * moment Firebase is connected. Every subclass writes its FCM payload now even
 * though nothing reads it yet, which is what keeps enabling push a
 * configuration change rather than a rewrite.
 */
abstract class DriverNotification extends Notification
{
    use Queueable;

    /**
     * A short machine-readable kind, e.g. "trip_added", used by the app to
     * pick an icon and to decide where tapping the notification leads.
     */
    abstract public function kind(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * Anything the app needs to act on the notification, such as a trip id.
     */
    public function payload(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! config('readyroute.push.enabled')) {
            return $channels;
        }

        $wantsPush = DriverPreferences::forUser($notifiable)[DriverPreferences::PUSH_NOTIFICATIONS] ?? true;

        if ($wantsPush && $notifiable->device_token) {
            $channels[] = \App\Notifications\Channels\FcmChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind'  => $this->kind(),
            'title' => $this->title(),
            'body'  => $this->body(),
            'data'  => $this->payload(),
        ];
    }

    /**
     * The Firebase message. Written now, delivered once push is enabled.
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => [
                'title' => $this->title(),
                'body'  => $this->body(),
            ],
            // FCM data values must be strings, so anything structured is
            // encoded rather than nested.
            'data' => array_map(
                fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
                array_merge(['kind' => $this->kind()], $this->payload())
            ),
        ];
    }
}
