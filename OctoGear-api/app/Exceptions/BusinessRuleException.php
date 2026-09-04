<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request violates an expected business rule
 * (e.g. an illegal status transition).
 *
 * These are expected, user-facing failures - they are rendered as a clean
 * 4xx response rather than a 500. Do not use this for unexpected errors.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  string|null  $messageKey  a localization key used to render the message
     * @param  array  $messageParams  parameters for the localized message
     * @param  int  $statusCode  HTTP status to return (defaults to 400, matching the ApiResponse error() helper)
     */
    public function __construct(
        string $message = '',
        private ?string $messageKey = null,
        private array $messageParams = [],
        private int $statusCode = 400,
    ) {
        parent::__construct($message);
    }

    public function messageKey(): ?string
    {
        return $this->messageKey;
    }

    public function messageParams(): array
    {
        return $this->messageParams;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
