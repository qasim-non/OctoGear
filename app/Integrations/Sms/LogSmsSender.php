<?php

namespace App\Integrations\Sms;

use App\Contracts\SmsSender;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/** Development adapter: preserves local logging; never sends an SMS. */
final class LogSmsSender implements SmsSender
{
    public function __construct(private Application $app) {}

    public function sendOtp(string $mobile, string $code): void
    {
        if ($this->app->environment('local')) {
            Log::info("OTP for {$mobile}: {$code}");
        }
    }
}
