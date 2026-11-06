<?php

namespace App\Middleware;

use App\Config\Config;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Enforces the shared API key.
 *
 * The key may be supplied via the X-API-Key header (preferred) or the api_key
 * query parameter. Comparison is timing-safe (hash_equals). Documentation,
 * health checks and OPTIONS preflight are exempt.
 *
 * CORS headers are added by the (outer) CorsMiddleware, so this middleware only
 * has to render the JSON 401 body.
 */
class ApiKeyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri()->getPath();
        $healthAlias = '/api/' . ($_ENV['API_VERSION'] ?? 'v1') . '/health';

        if (
            $request->getMethod() === 'OPTIONS'
            || $uri === '/'
            || $uri === '/health'
            || $uri === $healthAlias
            || strpos($uri, '/api-docs') === 0
        ) {
            return $handler->handle($request);
        }

        $provided = $request->getHeaderLine('X-API-Key');
        if ($provided === '') {
            $provided = (string) ($request->getQueryParams()['api_key'] ?? '');
        }

        $expected = (string) ($_ENV['API_KEY'] ?? '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid or missing API key',
            ]));

            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
