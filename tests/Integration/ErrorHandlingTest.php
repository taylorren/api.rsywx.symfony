<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

/**
 * Verifies the global JSON error-handling middleware end-to-end through the
 * real Slim stack (routes + middleware), mirroring public/index.php.
 */
final class ErrorHandlingTest extends BaseTestCase
{
    private function get(string $path): array
    {
        $request = $this->createRequest('GET', $path, ['X-API-Key' => 'test-api-key-12345']);
        $response = $this->runApp($request);
        $body = json_decode((string) $response->getBody(), true);

        return [$response, $body];
    }

    public function testUnknownRouteReturns404Json(): void
    {
        [$response, $body] = $this->get('/api/v1/definitely/not/a/route');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('message', $body);
    }

    public function testErrorResponsesCarryCorsHeaders(): void
    {
        [$response,] = $this->get('/api/v1/definitely/not/a/route');

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testMissingBookReturns404(): void
    {
        [$response, $body] = $this->get('/api/v1/books/99999');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Book not found', $body['message']);
    }

    public function testInvalidDateReturns400(): void
    {
        [$response, $body] = $this->get('/api/v1/books/today/13/40');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Invalid date: month must be 1-12, date must be 1-31', $body['message']);
    }

    public function testImpossibleDateReturns400(): void
    {
        [$response, $body] = $this->get('/api/v1/books/today/2/30');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Invalid date: the specified month and date combination does not exist', $body['message']);
    }

    public function testInvalidListTypeReturns400(): void
    {
        [$response, $body] = $this->get('/api/v1/books/list/invalidtype/value');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Invalid type', $body['message']);
    }

    public function testTagsOnMissingBookReturns404(): void
    {
        $stream = (new \Slim\Psr7\Factory\StreamFactory())
            ->createStream(json_encode(['tags' => ['test-tag']]));
        $request = $this->createRequest('POST', '/api/v1/books/99999/tags', [
            'X-API-Key' => 'test-api-key-12345',
            'Content-Type' => 'application/json',
        ])->withBody($stream);

        $response = $this->runApp($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Book not found', $body['message']);
    }

    public function testWeatherWithInvalidLocationMapsToClientOrUpstreamError(): void
    {
        // QWeather rejects plain city names (invalid parameter -> 400), while
        // an unreachable service yields 502. Both are correct outcomes of the
        // new mapping; the old behavior was a blanket 500.
        [$response, $body] = $this->get('/api/v1/misc/weather/forecast?location=beijing&days=2');

        $this->assertContains($response->getStatusCode(), [400, 502]);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('message', $body);
    }

    public function testUnconfiguredWeatherServiceReturnsClean500(): void
    {
        // Regression: ApiException was abstract once, so this path fataled with
        // "Cannot instantiate abstract class" instead of rendering a clean 500.
        $savedKey = $_ENV['QWEATHER_API_KEY'] ?? null;
        unset($_ENV['QWEATHER_API_KEY']);

        try {
            $request = $this->createRequest('GET', '/api/v1/misc/weather/current', [
                'X-API-Key' => 'test-api-key-12345',
            ]);
            $response = $this->runApp($request);
        } finally {
            if ($savedKey === null) {
                unset($_ENV['QWEATHER_API_KEY']);
            } else {
                $_ENV['QWEATHER_API_KEY'] = $savedKey;
            }
        }

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Weather service is not configured', $body['message']);
    }
}
