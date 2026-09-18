<?php

namespace App\Support;

use App\Models\User;

/**
 * The app preferences on the driver's settings screen. They live in user_metas
 * rather than their own columns because they are per-device conveniences, not
 * operational data.
 *
 * Push notifications is read by the notification classes to decide whether to
 * add the FCM channel, so this toggle controls real behaviour the moment push
 * is connected.
 */
class DriverPreferences
{
    public const PUSH_NOTIFICATIONS  = 'push_notifications';
    public const DARK_MODE           = 'dark_mode';
    public const OFFLINE_NAVIGATION  = 'offline_navigation';

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            self::PUSH_NOTIFICATIONS => true,
            self::DARK_MODE          => false,
            self::OFFLINE_NAVIGATION => true,
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    /**
     * Current preferences for a driver, with defaults filled in for anything
     * they have never set.
     *
     * @return array<string, bool>
     */
    public static function forUser(User $user): array
    {
        $preferences = [];

        foreach (self::defaults() as $key => $default) {
            $preferences[$key] = self::normalise($user->getMeta($key, $default));
        }

        return $preferences;
    }

    /**
     * Write only the preferences present in the payload, leaving the rest
     * untouched so a partial update never resets a toggle the app did not send.
     *
     * @return array<string, bool>
     */
    public static function update(User $user, array $payload): array
    {
        foreach (self::keys() as $key) {
            if (array_key_exists($key, $payload)) {
                $user->setMeta($key, self::normalise($payload[$key]) ? '1' : '0');
            }
        }

        $user->load('metas');

        return self::forUser($user);
    }

    /**
     * Meta values come back as strings, so "0" and "false" must not be read as
     * true the way a plain cast would.
     */
    private static function normalise(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
