<?php

namespace App\Services\Push;

use App\Exceptions\PushDeliveryException;
use App\Models\DeviceToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmSender
{
    public function __construct(private FcmAccessToken $auth) {}

    /** Returns false only when FCM explicitly reports an unregistered device. */
    public function send(DeviceToken $device, array $data, string $body): bool
    {
        $project = config('push.project_id');
        if (! is_string($project) || ! preg_match('/^[a-z0-9-]+$/D', $project)) {
            throw new RuntimeException('Invalid Firebase project configuration.');
        }
        try {
            $response = Http::withToken($this->auth->get())->acceptJson()
                ->connectTimeout(5)->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                    'message' => [
                        'token' => $device->token,
                        'notification' => ['title' => 'OctoGear', 'body' => $body],
                        'data' => $data,
                        'android' => [
                            'priority' => 'high',
                            'ttl' => '86400s',
                            'notification' => [
                                'channel_id' => 'octogear_updates',
                                'icon' => 'ic_notification',
                                'color' => '#242C41',
                                'tag' => $data['notification_id'],
                                'visibility' => 'PRIVATE',
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Firebase delivery connection failed.');
        }
        if ($response->successful()) {
            return true;
        }
        $errors = $response->json('error.details', []);
        foreach (is_array($errors) ? $errors : [] as $error) {
            if (($error['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && ($error['errorCode'] ?? null) === 'UNREGISTERED') {
                return false;
            }
        }
        // Generic 400/404 can be a bad payload or project, not an invalid token.
        if ($response->status() === 401) {
            $this->auth->forget();
        }
        $retryAfter = $response->header('Retry-After');
        $seconds = is_numeric($retryAfter) ? (int) $retryAfter : (strtotime($retryAfter) ?: time()) - time();
        throw new PushDeliveryException($response->status(), max(60, $seconds));
    }
}
