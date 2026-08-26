<?php

namespace App\Support\Api;

use RuntimeException;
use Throwable;

/**
 * The exception service classes throw to produce a specific cataloged error
 * code (see ErrorCode). Never let a controller build an error envelope by
 * hand — throw this and let the exception handler in bootstrap/app.php
 * render it consistently.
 */
class ApiException extends RuntimeException
{
    /** @param array<int, array<string, mixed>> $details */
    public function __construct(
        private readonly ErrorCode $errorCode,
        ?string $message = null,
        private readonly array $details = [],
        private readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? $errorCode->defaultMessage(), previous: $previous);
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /** @return array<int, array<string, mixed>> */
    public function details(): array
    {
        return $this->details;
    }

    public function status(): int
    {
        return $this->status ?? $this->errorCode->defaultStatus();
    }
}
