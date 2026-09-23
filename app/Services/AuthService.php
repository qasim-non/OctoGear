<?php

namespace App\Services;

use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function __construct(
        protected OtpService $otpService,
    ) {}

    public function sendOtp(string $mobile): void
    {
        $this->otpService->sendOtp($mobile);
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
                'token' => $this->otpService->createToken($user),
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

    public function register(string $tempToken, string $fullName, int $cityId): array
    {
        $mobile = $this->otpService->consumePendingToken('registration', $tempToken);

        if (! $mobile) {
            throw new BusinessRuleException(
                message: 'Registration temp token is invalid or expired',
                messageKey: 'auth.register.token_invalid',
                statusCode: 422,
            );
        }

        $user = $this->createUser($mobile, $fullName, $cityId);

        return [
            'token' => $this->otpService->createToken($user),
        ];
    }

    public function createUser(string $mobile, string $fullName, int $cityId): User
    {
        return User::create([
            'full_name' => $fullName,
            'mobile' => $mobile,
            'type' => UserType::Customer,
            'city_id' => $cityId,
            'status' => UserStatus::Unblocked,
        ]);
    }

    public function adminLogin(string $email, string $password): array
    {
        $admin = Admin::where('email', $email)->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            Log::warning('Admin login failed', ['email' => $email]);

            throw new BusinessRuleException(
                message: 'Invalid admin credentials',
                messageKey: 'auth.login.invalid',
                statusCode: 401,
            );
        }

        if ($admin->status === AdminStatus::Blocked) {
            Log::warning('Blocked admin login attempt', [
                'admin_id' => $admin->employee_id,
                'email' => $email,
            ]);

            throw new BusinessRuleException(
                message: 'Blocked admin login attempt',
                messageKey: 'auth.admin.blocked',
                statusCode: 403,
            );
        }

        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        return ['token' => $token];
    }
}
