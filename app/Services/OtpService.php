<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpService
{
    private const OTP_DECAY_MINUTES = 5;

    public function __construct(private SmsSender $sms) {}

    public function sendOtp(string $mobile): ?string
    {
        $otp = (string) random_int(1000, 9999);

        $hashed = Hash::make($otp);

        OtpCode::where('identifier', $mobile)->delete();

        OtpCode::create([
            'hashed_otp' => $hashed,
            'identifier' => $mobile,
            'expires_at' => now()->addMinutes(self::OTP_DECAY_MINUTES),
        ]);

        $this->sms->sendOtp($mobile, $otp);

        // Only explicitly enabled development/staging flows may expose codes.
        return $otp;
    }

    public function verifyOtp(string $mobile, string $otp): bool
    {
        $record = OtpCode::where('identifier', $mobile)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record || ! Hash::check($otp, $record->hashed_otp)) {
            Log::warning('OTP verification failed', [
                'mobile' => $mobile,
            ]);

            return false;
        }

        $record->delete();

        return true;
    }

    public function findByMobile(string $mobile): ?User
    {
        return User::where('mobile', $mobile)->first();
    }

    public function createPendingToken(string $intent, string $mobile): string
    {
        $token = Str::random(64);

        Cache::put("pending:{$intent}:{$token}", $mobile, now()->addMinutes(30));

        return $token;
    }

    public function consumePendingToken(string $intent, string $token): ?string
    {
        return Cache::pull("pending:{$intent}:{$token}");
    }

    public function createToken(User $user, array $abilities = ['*']): string
    {
        return $user->createToken('auth-token', $abilities)->plainTextToken;
    }
}
