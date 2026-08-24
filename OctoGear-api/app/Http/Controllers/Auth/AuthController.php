<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Admin;
use App\Services\OtpService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function sendOtp(SendOtpRequest $request)
    {
        $data = $request->validated();

        $this->otpService->sendOtp($data['mobile']);

        return $this->success(null, __('auth.otp.sent'));
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();

        $valid = $this->otpService->verifyOtp($data['mobile'], $data['otp']);

        if (!$valid) {
            return $this->error(__('auth.otp.rate_limited', ['max' => 5]), 422);
        }

        $user = $this->otpService->findByMobile($data['mobile']);

        if ($user) {
            $token = $this->otpService->createToken($user);

            return $this->success([
                'token'  => $token,
                'is_new' => false,
            ], __('auth.login.success'));
        }

        $tempToken = $this->otpService->createPendingRegistration($data['mobile']);

        return $this->success([
            'temp_token' => $tempToken,
            'is_new'     => true,
        ], __('auth.otp.verified'));
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $mobile = $this->otpService->consumePendingRegistration($data['temp_token']);

        if (!$mobile) {
            return $this->error(__('auth.register.token_invalid'), 422);
        }

        $user = $this->otpService->createUser(
            $mobile,
            $data['full_name'],
            $data['city_id']
        );

        $token = $this->otpService->createToken($user);

        return $this->success([
            'token' => $token,
        ], __('auth.register.completed'));
    }

    public function adminLogin(AdminLoginRequest $request)
    {
        $email = $request->validated('email');

        $key = "admin_login:{$email}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->error(__('auth.login.rate_limited'), 429);
        }

        $admin = Admin::where('email', $email)->first();

        if (!$admin || !Hash::check($request->validated('password'), $admin->password)) {
            RateLimiter::hit($key, 300);
            Log::warning('Admin login failed', ['email' => $email]);

            return $this->error(__('auth.login.invalid'), 401);
        }

        if ($admin->status === AdminStatus::Blocked) {
            Log::warning('Blocked admin login attempt', [
                'admin_id' => $admin->employee_id,
                'email'    => $email,
            ]);

            return $this->error(__('auth.admin.blocked'), 403);
        }

        RateLimiter::clear($key);

        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        return $this->success([
            'token' => $token,
        ], __('auth.login.success'));
    }
}
