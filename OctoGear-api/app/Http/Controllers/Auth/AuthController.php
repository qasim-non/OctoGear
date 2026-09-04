<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function sendOtp(SendOtpRequest $request)
    {
        $this->authService->sendOtp($request->validated('mobile'));

        return $this->success(null, __('auth.otp.sent'));
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $result = $this->authService->verifyOtp(
            $request->validated('mobile'),
            $request->validated('otp'),
        );

        return $this->success(
            $result,
            $result['is_new'] ? __('auth.otp.verified') : __('auth.login.success')
        );
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $result = $this->authService->register(
            $data['temp_token'],
            $data['full_name'],
            $data['city_id'],
        );

        return $this->success(['token' => $result['token']], __('auth.register.completed'));
    }

    public function adminLogin(AdminLoginRequest $request)
    {
        $result = $this->authService->adminLogin(
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->success(['token' => $result['token']], __('auth.login.success'));
    }
}
