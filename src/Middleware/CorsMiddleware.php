<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Adds the CORS headers to every response and short-circuits preflight OPTIONS.
 *
 * Registered outermost so error responses (404/401/5xx) also carry the CORS
 * headers — the frontend runs on a different origin and must be able to read
 * error bodies.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private const ALLOW_HEADERS = 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key';
    private const ALLOW_METHODS = 'GET, POST, PUT, DELETE, OPTIONS';

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->decorate(new SlimResponse())->withStatus(200);
        }

        return $this->decorate($handler->handle($request));
    }

    private function decorate(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', self::ALLOW_HEADERS)
            ->withHeader('Access-Control-Allow-Methods', self::ALLOW_METHODS)
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
