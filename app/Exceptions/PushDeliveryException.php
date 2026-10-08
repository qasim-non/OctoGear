<?php

namespace App\Exceptions;

use RuntimeException;

class PushDeliveryException extends RuntimeException
{
    public function __construct(public int $status, public ?int $retryAfter = null)
    {
        parent::__construct('Firebase delivery failed (HTTP '.$status.').');
    }

    public function retryable(): bool
    {
        return $this->status === 401 || $this->status === 429 || $this->status >= 500;
    }
}
