<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceTokenService
{
    public function __construct(private DeviceTokenRepository $devices) {}

    public function register(User $user, array $data): void
    {
        DB::transaction(fn () => $this->devices->register($user, $user->currentAccessToken(), $data), 3);
    }

    public function unregister(User $user): void
    {
        DB::transaction(fn () => $this->devices->unregister($user->currentAccessToken()), 3);
    }

    public function logout(User|Admin $user): void
    {
        $session = $user->currentAccessToken();
        if ($session instanceof PersonalAccessToken) {
            DB::transaction(function () use ($session) {
                $this->devices->unregister($session);
                $session->delete();
            }, 3);
        }
    }
}
