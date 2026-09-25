<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

/**
 * End-to-end tests for the /api/v1/blogs endpoints through the real Slim stack.
 * Requires a reachable WordPress database (configured via WP_DB_* in .env).
 */
final class BlogsEndpointsTest extends BaseTestCase
{
    private function get(string $path): array
    {
        $request = $this->createRequest('GET', $path, ['X-API-Key' => 'test-api-key-12345']);
        $response = $this->runApp($request);
        $body = json_decode((string) $response->getBody(), true);

        return [$response, $body];
    }

    public function testTodaysPostsEndpoint(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/today');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('date_info', $body);
        $this->assertArrayHasKey('count', $body);
        $this->assertArrayHasKey('cached', $body);
        $this->assertTrue($body['date_info']['is_today']);

        // Current year must be excluded: every post is from a previous year
        foreach ($body['data'] as $post) {
            $this->assertLessThan(
                (int)date('Y'),
                (int)date('Y', strtotime($post['post_date'])),
                'Posts for "on this day" must exclude the current year'
            );
            $this->assertArrayHasKey('permalink', $post);
            $this->assertArrayHasKey('years_ago', $post);
        }
    }

    public function testPostsOnSpecificDate(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/today/9/5');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame(9, $body['date_info']['month']);
        $this->assertSame(5, $body['date_info']['day']);
        $this->assertSame('September 5', $body['date_info']['date_string']);
        $this->assertFalse($body['date_info']['is_today']);

        foreach ($body['data'] as $post) {
            $this->assertSame('09', date('m', strtotime($post['post_date'])));
            $this->assertSame('05', date('d', strtotime($post['post_date'])));
        }
    }

    public function testInvalidMonthReturns400(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/today/13/40');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Invalid date parameters', $body['message']);
    }

    public function testImpossibleDateReturns400(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/today/2/30');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Invalid date', $body['message']);
    }

    public function testLatestPostsDefaultsToThree(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/latest');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['data']);
        $this->assertLessThanOrEqual(3, count($body['data']));
        $this->assertArrayHasKey('cached', $body);

        // Verify chronological order (newest first)
        $dates = array_column($body['data'], 'post_date');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);

        foreach ($body['data'] as $post) {
            $this->assertNotEmpty($post['post_excerpt'], 'Excerpt fallback must always produce text');
            $this->assertLessThanOrEqual(153, mb_strlen($post['post_excerpt']));
            $this->assertArrayNotHasKey('post_content', $post, 'Raw content must not leak into list responses');
            $this->assertArrayHasKey('permalink', $post);
            $this->assertStringEndsWith('/', $post['permalink']);
            $this->assertSame('publish', $post['post_status']);
        }
    }

    public function testLatestPostsWithCustomCount(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/latest/5');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertLessThanOrEqual(5, count($body['data']));
    }

    public function testLatestPostsClampsCountToRange(): void
    {
        // Route allows any digit string; controller clamps to 1..50
        [$response, $body] = $this->get('/api/v1/blogs/latest/9999');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertLessThanOrEqual(50, count($body['data']));
    }

    public function testRefreshQueryParameterIsAccepted(): void
    {
        [$response, $body] = $this->get('/api/v1/blogs/latest?refresh=true');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertFalse($body['cached']);
    }

    public function testOldWpRouteIsRemoved(): void
    {
        [$response, $body] = $this->get('/api/v1/wp/posts/today');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('message', $body);
    }
}