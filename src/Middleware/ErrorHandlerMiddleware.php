<?php

namespace App\Middleware;

use App\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * Renders every exception as a consistent JSON error body:
 *   { "success": false, "message": "..." [, "detail": "..."] }
 *
 * Status code mapping:
 *   - App\Exceptions\ApiException       → its own status code (400/404/502/...)
 *   - Slim\Exception\HttpException      → its own status code (routing 404/405)
 *   - \InvalidArgumentException        → 400 (legacy throws from models)
 *   - anything else                     → 500 with a generic message
 *
 * Raw exception details are exposed only when APP_ENV=development; in
 * production, sensitive context travels via ApiException::$detail and is
 * stripped from the response.
 *
 * This middleware is registered innermost (added before the core error
 * middleware in index.php) so rendered error responses still pass back
 * through the CORS and timing middleware on the way out.
 */
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (ApiException $e) {
            return $this->render($e->getStatusCode(), $e->getMessage(), $e->getDetail() ?: null);
        } catch (HttpException $e) {
            return $this->render($e->getCode(), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->render(400, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->render(500, $e->getMessage(), (string) $e, true);
        }
    }

    private function render(int $status, string $message, ?string $detail = null, bool $genericInProduction = false): Response
    {
        $dev = ($_ENV['APP_ENV'] ?? 'production') === 'development';

        if ($genericInProduction && !$dev) {
            $message = 'Internal server error';
        }

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($detail !== null && $dev) {
            $payload['detail'] = $detail;
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode($payload));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
