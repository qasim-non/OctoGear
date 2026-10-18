<?php

namespace App\Data;

final readonly class PaymentRefund
{
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public int $amount,
    ) {}

    public function idempotencyKey(): string
    {
        return 'payment-'.$this->paymentId.'-refund';
    }

    public function currency(): string
    {
        return 'SAR';
    }
}
