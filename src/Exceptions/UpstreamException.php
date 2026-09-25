<?php

namespace App\Exceptions;

/**
 * Thrown when a third-party service (QWeather, WordPress, ...) fails (HTTP 502).
 * The public message stays generic; raw upstream responses go into $detail.
 */
class UpstreamException extends ApiException
{
    public function __construct(string $message = 'Upstream service error', ?string $detail = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 502, $detail, $previous);
    }
}
