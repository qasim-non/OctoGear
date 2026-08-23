<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OtpService
{
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_DECAY_MINUTES = 5;

    public function sendOtp(string $mobile): string
    {
        $otp = fake()->numerify('####');

        $hashed = Hash::make($otp);

        OtpCode::where('identifier', $mobile)->delete();

        OtpCode::create([
            'hashed_otp' => $hashed,
            'identifier' => $mobile,
            'expires_at' => now()->addMinutes(self::OTP_DECAY_MINUTES),
        ]);

        if (app()->environment('local')) {
            Log::info("OTP for {$mobile}: {$otp}");
        }

        return $otp;
    }

    public function verifyOtp(string $mobile, string $otp): bool
    {
        $key = "otp_verify:{$mobile}";

        if (RateLimiter::tooManyAttempts($key, self::OTP_MAX_ATTEMPTS)) {
            Log::warning('OTP rate limited', [
                'mobile' => $mobile,
            ]);

            return false;
        }

        $record = OtpCode::where('identifier', $mobile)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$record || !Hash::check($otp, $record->hashed_otp)) {
            RateLimiter::hit($key, self::OTP_DECAY_MINUTES);

            Log::warning('OTP verification failed', [
                'mobile' => $mobile,
            ]);

            return false;
        }

        $record->delete();
        RateLimiter::clear($key);

        return true;
    }

    public function findByMobile(string $mobile): ?User
    {
        return User::where('mobile', $mobile)->first();
    }

    public function createPendingRegistration(string $mobile): string
    {
        $token = Str::random(64);

        Cache::put("pending_registration:{$token}", $mobile, now()->addMinutes(30));

        return $token;
    }

    public function consumePendingRegistration(string $token): ?string
    {
        $key = "pending_registration:{$token}";
        $lockKey = "lock:{$key}";

        $lock = Cache::lock($lockKey, 10);

        try {
            $mobile = Cache::get($key);

            if (!$mobile) {
                return null;
            }

            Cache::forget($key);

            return $mobile;
        } finally {
            $lock->release();
        }
    }

    public function createUser(string $mobile, string $fullName, int $cityId): User
    {
        return User::create([
            'full_name' => $fullName,
            'mobile'    => $mobile,
            'type'      => UserType::Customer,
            'city_id'   => $cityId,
            'status'    => UserStatus::Unblocked,
        ]);
    }

    public function createToken(User $user, array $abilities = ['*']): string
    {
        return $user->createToken('auth-token', $abilities)->plainTextToken;
    }
}
