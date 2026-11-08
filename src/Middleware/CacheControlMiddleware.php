<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\StreamFactory;

/**
 * HTTP caching for the public API.
 *
 * Successful reads get a short public lifetime plus a strong ETag, so a repeat
 * request costs a 304 instead of a payload. Everything else — writes, errors and
 * anything under /admin — is `no-store`, which is also what keeps a 401 or a 500
 * from being cached by an intermediary.
 *
 * The frontend does not depend on this: it caches catalogue data server-side
 * (APCu, 24 h for book details) and calls the API from Node. This is for every
 * other consumer, and for any future CDN in front of the API.
 */
class CacheControlMiddleware implements MiddlewareInterface
{
    /** Seconds a read response may be reused without asking again. */
    private const MAX_AGE = 30;

    /** Seconds a stale read may still be served while it revalidates. */
    private const STALE_WHILE_REVALIDATE = 300;

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        $status = $response->getStatusCode();
        $isRead = \in_array($request->getMethod(), ['GET', 'HEAD'], true);
        $isOk = $status >= 200 && $status < 300;
        $path = $request->getUri()->getPath();

        if (!$isRead || !$isOk || str_starts_with($path, '/admin')) {
            return $response->withHeader('Cache-Control', 'no-store');
        }

        $body = $response->getBody();
        $payload = (string) $body;
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $etag = '"' . substr(hash('sha256', $payload), 0, 32) . '"';

        $response = $response
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', sprintf(
                'public, max-age=%d, stale-while-revalidate=%d',
                self::MAX_AGE,
                self::STALE_WHILE_REVALIDATE
            ));

        if ($this->matches($request->getHeaderLine('If-None-Match'), $etag)) {
            return $response
                ->withStatus(304)
                ->withoutHeader('Content-Length')
                ->withBody((new StreamFactory())->createStream());
        }

        return $response;
    }

    /** Compare If-None-Match against the current validator (RFC 9110 §13.1.2). */
    private function matches(string $header, string $etag): bool
    {
        if ($header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || $candidate === $etag) {
                return true;
            }
            // Weak form: W/"abc" matches the strong "abc" for cache validation.
            if (str_starts_with($candidate, 'W/') && substr($candidate, 2) === $etag) {
                return true;
            }
        }

        return false;
    }
}
