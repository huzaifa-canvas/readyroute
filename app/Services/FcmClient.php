<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Minimal Firebase Cloud Messaging client for the HTTP v1 API.
 *
 * FCM v1 authenticates with a short-lived OAuth token minted from a service
 * account key, rather than the legacy server key. That exchange is done here
 * and cached, so a burst of notifications costs one token request rather than
 * one per message.
 */
class FcmClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY = 'fcm.access_token';

    public function isConfigured(): bool
    {
        return (bool) config('readyroute.push.firebase.project_id')
            && is_string(config('readyroute.push.firebase.credentials_path'))
            && is_file((string) config('readyroute.push.firebase.credentials_path'));
    }

    /**
     * Send one message to one device.
     *
     * Returns false rather than throwing for ordinary delivery failures: a
     * notification is already stored in the database, and push is the extra.
     */
    public function send(string $deviceToken, array $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('FCM is enabled but not configured; skipping push.');

            return false;
        }

        try {
            $projectId = config('readyroute.push.firebase.project_id');

            $response = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => array_merge($message, ['token' => $deviceToken]),
                ]);

            if ($response->successful()) {
                return true;
            }

            // A token for an app that has been uninstalled or reinstalled will
            // never work again, and the caller clears it.
            if ($this->isDeadToken($response->json())) {
                throw new DeadDeviceTokenException();
            }

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return false;
        } catch (DeadDeviceTokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('FCM send errored', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function isDeadToken(?array $body): bool
    {
        $status = $body['error']['status'] ?? null;

        if ($status === 'NOT_FOUND' || $status === 'UNREGISTERED') {
            return true;
        }

        foreach ($body['error']['details'] ?? [] as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }

    /**
     * A cached OAuth access token, minted by signing a JWT with the service
     * account key. Cached slightly under its real lifetime so it is never used
     * in the moment it expires.
     */
    private function accessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(55), function () {
            $credentials = $this->credentials();

            $now = time();

            $claims = [
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => self::TOKEN_URL,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ];

            $jwt = $this->signJwt($claims, $credentials['private_key']);

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                throw new RuntimeException('Could not obtain an FCM access token.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function credentials(): array
    {
        $path = (string) config('readyroute.push.firebase.credentials_path');

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('The Firebase credentials file is not a valid service account key.');
        }

        return $json;
    }

    private function signJwt(array $claims, string $privateKey): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $segments = [
            $encode(['alg' => 'RS256', 'typ' => 'JWT']),
            $encode($claims),
        ];

        $input = implode('.', $segments);

        $signature = '';

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Firebase authentication request.');
        }

        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
