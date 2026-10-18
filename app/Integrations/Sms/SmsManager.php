<?php

namespace App\Integrations\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Manager;
use InvalidArgumentException;

final class SmsManager extends Manager implements SmsSender
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('sms.driver', 'log');
    }

    protected function createDriver($driver): SmsSender
    {
        $class = $this->config->get("sms.drivers.{$driver}");

        if (! is_string($class) || ! is_a($class, SmsSender::class, true)) {
            throw new InvalidArgumentException("SMS driver [{$driver}] is not implemented.");
        }

        return $this->container->make($class);
    }

    public function sendOtp(string $mobile, string $code): void
    {
        $this->driver()->sendOtp($mobile, $code);
    }
}
