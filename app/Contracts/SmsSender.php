<?php

namespace App\Contracts;

interface SmsSender
{
    /** Send the generated code, or throw when delivery is rejected. */
    public function sendOtp(string $mobile, string $code): void;
}
