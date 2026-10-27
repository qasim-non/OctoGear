<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Responses\AuthSessionResponse;
use App\Services\AuthService;
use App\Services\DeviceTokenService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        private AuthSessionResponse $sessions,
    ) {}

    public function sendOtp(SendOtpRequest $request)
    {
        $testOtp = $this->authService->sendOtp($request->validated('mobile'));

        return $this->success($testOtp === null ? null : ['test_otp' => $testOtp], __('auth.otp.sent'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $result = $this->authService->verifyOtp(
            $request->validated('mobile'),
            $request->validated('otp'),
        );

        return $this->sessions->make($request,
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
            PreferredLanguage::tryFrom($data['preferred_language'] ?? $request->header('Accept-Language', '')),
        );

        return $this->sessions->make($request, $result, __('auth.register.completed'));
    }

    public function logout(Request $request, DeviceTokenService $devices)
    {
        $devices->logout($request->user());

        return $this->sessions->clear($this->success(null, __('auth.logout.success')));
    }

    public function adminLogin(AdminLoginRequest $request)
    {
        $result = $this->authService->adminLogin(
            $request->validated('email'),
            $request->validated('password'),
            $request->enum('preferred_language', PreferredLanguage::class),
            $request->header('X-Auth-Client') === 'web',
        );

        return $this->sessions->make($request, $result, __('auth.login.success'));
    }
}
