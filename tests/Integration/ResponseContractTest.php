<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

/**
 * Response-contract assertions: locks the documented wire types and shapes so
 * the list/detail/reading/WordPress responses can't drift again. This is the
 * contract the (rewritten) frontend will type against.
 */
final class ResponseContractTest extends BaseTestCase
{
    /**
     * @return array{0:int, 1:array}
     */
    private function get(string $path): array
    {
        $request = $this->createRequest('GET', $path, ['X-API-Key' => 'test-api-key-12345']);
        $response = $this->runApp($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function assertNumberOrNull($value, string $message): void
    {
        $this->assertTrue(
            $value === null || is_int($value) || is_float($value),
            $message . ' (got ' . gettype($value) . ')'
        );
    }

    public function testBookListTypes(): void
    {
        [$status, $body] = $this->get('/api/v1/books/list/title/-/1');
        $this->assertSame(200, $status);
        $book = $body['data'][0];

        $this->assertIsInt($book['id']);
        $this->assertIsString($book['bookid']);
        $this->assertIsString($book['title']);
        $this->assertIsString($book['author']);
        $this->assertIsString($book['cover_uri']);
        $this->assertIsArray($book['tags']);
        $this->assertIsBool($book['translated'], 'list translated must be boolean');
        $this->assertNumberOrNull($book['price'], 'list price must be a number or null');
    }

    public function testBookDetailTypes(): void
    {
        [$status, $body] = $this->get('/api/v1/books/00666?visit=false');
        $this->assertSame(200, $status);
        $book = $body['data'];

        $this->assertIsInt($book['id']);
        $this->assertIsString($book['bookid']);
        $this->assertIsString($book['cover_uri']);
        $this->assertIsArray($book['tags']);
        $this->assertIsArray($book['reviews']);
        $this->assertIsBool($book['translated'], 'detail translated must be boolean');
        $this->assertIsBool($book['instock'], 'detail instock must be boolean');
        $this->assertIsInt($book['total_visits']);
        $this->assertNumberOrNull($book['price'], 'detail price must be a number or null');
    }

    public function testListAndDetailAgreeOnSharedTypes(): void
    {
        [, $list] = $this->get('/api/v1/books/list/title/-/1');
        [, $detail] = $this->get('/api/v1/books/00666?visit=false');
        $l = $list['data'][0];
        $d = $detail['data'];

        $this->assertSame(gettype($l['translated']), gettype($d['translated']), 'translated type must match');
        $this->assertIsBool($l['translated']);
        $this->assertSame(gettype($l['id']), gettype($d['id']));
        $this->assertSame(gettype($l['bookid']), gettype($d['bookid']));
        // price is "number|null" in both.
        $this->assertNumberOrNull($l['price'], 'list price');
        $this->assertNumberOrNull($d['price'], 'detail price');
    }

    public function testReadingsSummaryTypes(): void
    {
        [$status, $body] = $this->get('/api/v1/readings/summary');
        $this->assertSame(200, $status);
        $d = $body['data'];

        $this->assertIsInt($d['books_read']);
        $this->assertIsInt($d['reviews_written']);
        $this->assertIsArray($d['reading_period']);
        $this->assertIsString($d['reading_period']['earliest_date']);
        $this->assertIsString($d['reading_period']['latest_date']);
        $this->assertIsInt($d['reading_period']['total_days']);
    }

    public function testReadingsReviewShapeTypes(): void
    {
        [$status, $body] = $this->get('/api/v1/readings/reviews/1');
        $this->assertSame(200, $status);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('pagination', $body);

        if (!empty($body['data'])) {
            $r = $body['data'][0];
            foreach (['title', 'datein', 'uri', 'bookid', 'book_title', 'cover_uri'] as $key) {
                $this->assertArrayHasKey($key, $r);
                $this->assertIsString($r[$key], "reviews.$key must be a string");
            }
            $this->assertArrayHasKey('feature', $r);
            $this->assertTrue(
                $r['feature'] === null || is_string($r['feature']),
                'reviews.feature must be a string or null'
            );
        }
    }

    public function testBooksTodayDateInfoTypes(): void
    {
        [$status, $body] = $this->get('/api/v1/books/today');
        $this->assertSame(200, $status);
        $d = $body['date_info'];

        $this->assertIsString($d['requested_date']);
        $this->assertIsString($d['month_day']);
        $this->assertIsBool($d['is_today'], 'books date_info.is_today must be boolean');
    }

    public function testBlogsTodayDateInfoTypes(): void
    {
        [$status, $body] = $this->get('/api/v1/blogs/today');
        if ($status === 502) {
            $this->markTestSkipped('WordPress database unavailable');
        }
        $this->assertSame(200, $status);
        $d = $body['date_info'];

        $this->assertIsInt($d['month']);
        $this->assertIsInt($d['day']);
        $this->assertIsString($d['date_string']);
        $this->assertIsBool($d['is_today'], 'WordPress date_info.is_today must be boolean');
    }
}
