<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use Illuminate\Support\Facades\DB;

class DeviceTokenService
{
    public function __construct(private DeviceTokenRepository $devices, private AuthSessionService $sessions) {}

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
        $this->sessions->logout($user);
    }
}
