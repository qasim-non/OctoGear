<?php

namespace App\Data;

final readonly class PaymentCharge
{
    public function __construct(
        public int $orderId,
        public int $amount,
        #[\SensitiveParameter] public ?string $cardToken,
    ) {}

    public function idempotencyKey(): string
    {
        return 'order-'.$this->orderId.'-charge';
    }

    public function currency(): string
    {
        return 'SAR';
    }
}
