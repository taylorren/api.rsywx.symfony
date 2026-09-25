<?php

namespace Tests\Unit;

use App\Exceptions\NotFoundException;
use App\Exceptions\UpstreamException;
use App\Exceptions\ValidationException;
use App\Middleware\ErrorHandlerMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    private $savedAppEnv;

    protected function setUp(): void
    {
        $this->savedAppEnv = $_ENV['APP_ENV'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->savedAppEnv === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $this->savedAppEnv;
        }
    }

    private function process(\Throwable $thrown = null, string $appEnv = 'production'): ResponseInterface
    {
        $_ENV['APP_ENV'] = $appEnv;

        $middleware = new ErrorHandlerMiddleware();
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/books/status');
        $handler = new ThrowingHandler($thrown);

        return $middleware->process($request, $handler);
    }

    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    public function testSuccessResponsePassesThroughUntouched(): void
    {
        $response = $this->process(null);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testNotFoundExceptionMapsTo404(): void
    {
        $response = $this->process(new NotFoundException('Book not found'));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = $this->decode($response);
        $this->assertFalse($body['success']);
        $this->assertSame('Book not found', $body['message']);
        $this->assertArrayNotHasKey('detail', $body);
    }

    public function testValidationExceptionMapsTo400(): void
    {
        $response = $this->process(new ValidationException('Tags array is required'));
        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertFalse($body['success']);
        $this->assertSame('Tags array is required', $body['message']);
    }

    public function testUpstreamExceptionMapsTo502(): void
    {
        $response = $this->process(new UpstreamException('Weather service error', 'raw upstream stuff'));
        $this->assertSame(502, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertFalse($body['success']);
        $this->assertSame('Weather service error', $body['message']);
    }

    public function testInvalidArgumentExceptionMapsTo400(): void
    {
        $response = $this->process(new \InvalidArgumentException('Bad input'));
        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame('Bad input', $body['message']);
    }

    public function testGenericExceptionMapsTo500WithGenericMessageInProduction(): void
    {
        $response = $this->process(new \RuntimeException('SQLSTATE[42S02]: secret internals'), 'production');
        $this->assertSame(500, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertFalse($body['success']);
        $this->assertSame('Internal server error', $body['message']);
        $this->assertArrayNotHasKey('detail', $body);
    }

    public function testGenericExceptionShowsDetailInDevelopment(): void
    {
        $response = $this->process(new \RuntimeException('SQLSTATE[42S02]: secret internals'), 'development');
        $this->assertSame(500, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame('SQLSTATE[42S02]: secret internals', $body['message']);
        $this->assertArrayHasKey('detail', $body);
        $this->assertStringContainsString('RuntimeException', $body['detail']);
    }

    public function testApiExceptionDetailHiddenInProductionButMessageKept(): void
    {
        $response = $this->process(
            new UpstreamException('Weather service error', 'https://secret-host/api responded with junk'),
            'production'
        );
        $body = $this->decode($response);
        $this->assertSame(502, $response->getStatusCode());
        $this->assertSame('Weather service error', $body['message']);
        $this->assertArrayNotHasKey('detail', $body);
        $this->assertStringNotContainsString('secret-host', (string) $response->getBody());
    }

    public function testApiExceptionDetailExposedInDevelopment(): void
    {
        $response = $this->process(
            new UpstreamException('Weather service error', 'https://secret-host/api responded with junk'),
            'development'
        );
        $body = $this->decode($response);
        $this->assertSame('Weather service error', $body['message']);
        $this->assertStringContainsString('secret-host', $body['detail']);
    }
}

/**
 * Request handler that throws the given exception (or returns a bare 200).
 */
final class ThrowingHandler implements RequestHandlerInterface
{
    private $thrown;

    public function __construct(?\Throwable $thrown)
    {
        $this->thrown = $thrown;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->thrown !== null) {
            throw $this->thrown;
        }
        return new \Slim\Psr7\Response();
    }
}
