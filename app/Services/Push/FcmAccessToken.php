<?php

namespace App\Services\Push;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class FcmAccessToken
{
    private function cacheKey(): string
    {
        return 'fcm.oauth.'.hash('sha256', config('push.project_id').'|'.config('push.credentials'));
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function get(): string
    {
        $path = config('push.credentials');
        $key = $this->cacheKey();
        if ($cached = Cache::get($key)) {
            return $cached;
        }
        try {
            $scope = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = $path
                ? new ServiceAccountCredentials($scope, $path)
                : ApplicationDefaultCredentials::getCredentials($scope);
            $handler = HttpHandlerFactory::build(new Client(['timeout' => 10, 'connect_timeout' => 5]), false);
            $response = $credentials->fetchAuthToken($handler);
            $token = $response['access_token'] ?? null;
            if (! is_string($token) || $token === '') {
                throw new RuntimeException;
            }

            $seconds = max(1, min(3000, (int) ($response['expires_in'] ?? 300) - 60));
            Cache::put($key, $token, $seconds);

            return $token;
        } catch (Throwable) {
            // Never put private keys, OAuth responses or credentials in failed jobs.
            throw new RuntimeException('Firebase authentication failed. Check server credentials.');
        }
    }
}
