<?php

namespace App\Exceptions;

/**
 * Thrown when a requested resource does not exist (HTTP 404).
 */
class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Resource not found', ?string $detail = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $detail, $previous);
    }
}
