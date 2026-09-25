<?php

namespace App\Exceptions;

/**
 * Base class for all API exceptions that carry a proper HTTP status code.
 *
 * Can be used directly for generic API errors (e.g. server misconfiguration
 * with HTTP 500); prefer the subclasses below for the common cases.
 *
 * When thrown, the ErrorHandlerMiddleware renders
 * { "success": false, "message": ... } using getStatusCode() as the HTTP status.
 *
 * Messages are considered safe for public consumption. Anything sensitive
 * (URLs, raw upstream responses, stack traces) belongs in $detail, which is
 * only exposed in the response body when APP_ENV=development.
 */
class ApiException extends \Exception
{
    private string $detail;

    public function __construct(string $message, int $statusCode, ?string $detail = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->detail = $detail ?? '';
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function getDetail(): string
    {
        return $this->detail;
    }
}
