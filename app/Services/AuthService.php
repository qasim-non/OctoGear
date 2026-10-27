<?php

namespace App\Services;

use App\Enums\PreferredLanguage;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\User;

class AuthService
{
    public function __construct(
        protected OtpService $otpService,
        protected AdminCredentialService $adminCredentials,
        protected AuthSessionService $sessions,
    ) {}

    public function sendOtp(string $mobile): ?string
    {
        return $this->otpService->sendOtp($mobile);
    }

    public function verifyOtp(string $mobile, string $otp): array
    {
        if (! $this->otpService->verifyOtp($mobile, $otp)) {
            throw new BusinessRuleException(
                message: 'OTP verification failed',
                messageKey: 'auth.otp.rate_limited',
                messageParams: ['max' => 5],
                statusCode: 422,
            );
        }

        $user = $this->otpService->findByMobile($mobile);

        if ($user) {
            return [
                ...$this->sessions->start($user),
                'is_new' => false,
                'type' => $user->type->value,
            ];
        }

        return [
            'temp_token' => $this->otpService->createPendingToken('registration', $mobile),
            'is_new' => true,
            'type' => null,
        ];
    }

    public function register(
        string $tempToken,
        string $fullName,
        int $cityId,
        ?PreferredLanguage $preferredLanguage = null,
    ): array {
        $mobile = $this->otpService->consumePendingToken('registration', $tempToken);

        if (! $mobile) {
            throw new BusinessRuleException(
                message: 'Registration temp token is invalid or expired',
                messageKey: 'auth.register.token_invalid',
                statusCode: 422,
            );
        }

        $user = $this->createUser($mobile, $fullName, $cityId, $preferredLanguage);

        return $this->sessions->start($user);
    }

    public function createUser(
        string $mobile,
        string $fullName,
        int $cityId,
        ?PreferredLanguage $preferredLanguage = null,
    ): User {
        return User::create([
            'full_name' => $fullName,
            'preferred_language' => $preferredLanguage,
            'mobile' => $mobile,
            'type' => UserType::Customer,
            'city_id' => $cityId,
            'status' => UserStatus::Unblocked,
        ]);
    }

    public function adminLogin(string $email, string $password, ?PreferredLanguage $preferredLanguage = null, bool $browser = false): array
    {
        return $this->adminCredentials->login($email, $password, $preferredLanguage, $browser);
    }
}
