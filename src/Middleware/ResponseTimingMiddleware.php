<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Adds an X-Response-Time header (milliseconds). Only registered when
 * APP_ENV=development.
 */
class ResponseTimingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $start = microtime(true);

        $response = $handler->handle($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        return $response->withHeader('X-Response-Time', $duration . 'ms');
    }
}
