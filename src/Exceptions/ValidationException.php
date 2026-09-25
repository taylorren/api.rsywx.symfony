<?php

namespace App\Exceptions;

/**
 * Thrown when the client supplied invalid input (HTTP 400).
 */
class ValidationException extends ApiException
{
    public function __construct(string $message = 'Invalid request parameters', ?string $detail = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $detail, $previous);
    }
}
