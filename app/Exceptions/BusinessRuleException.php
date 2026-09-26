<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request violates an expected business rule
 * (e.g. an illegal status transition).
 *
 * These are expected, user-facing failures. They are rendered as a safe API
 * response rather than leaking implementation details. Do not use this for
 * unexpected errors.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  string|null  $messageKey  a localization key used to render the message
     * @param  array  $messageParams  parameters for the localized message
     * @param  int  $statusCode  HTTP status to return (defaults to 400, matching the ApiResponse error() helper)
     * @param  array<string, array<int, string>>  $errors  optional field errors for expected validation/business failures
     */
    public function __construct(
        string $message = '',
        private ?string $messageKey = null,
        private array $messageParams = [],
        private int $statusCode = 400,
        private array $errors = [],
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

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
