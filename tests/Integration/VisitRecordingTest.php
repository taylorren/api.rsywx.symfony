<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

/**
 * Verifies the "read is not a visit" split: GET /books/{id} records a visit by
 * default, but ?visit=false (prefetch / hover-preload, post-tag-refresh) must
 * not inflate the counter. The optional alias ?record_visit=... behaves the same.
 */
final class VisitRecordingTest extends BaseTestCase
{
    private const BOOKID = '00666';

    protected function setUp(): void
    {
        parent::setUp();
        // Never call the external geolocation service from tests.
        $_ENV['GEOLOCATION_ENABLED'] = 'false';
    }

    protected function tearDown(): void
    {
        unset($_ENV['GEOLOCATION_ENABLED']);
        parent::tearDown();
    }

    /**
     * @return array{0:int, 1:array}
     */
    private function detail(string $query = ''): array
    {
        $request = $this->createRequest('GET', '/api/v1/books/' . self::BOOKID . $query, [
            'X-API-Key' => 'test-api-key-12345',
        ]);
        $response = $this->runApp($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    public function testDetailReadIsServed(): void
    {
        [$status, $body] = $this->detail('?visit=false');

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('total_visits', $body['data']);
    }

    public function testVisitFalseDoesNotRecordAVisit(): void
    {
        [, $first] = $this->detail('?visit=false');
        [, $second] = $this->detail('?visit=false');
        [, $third] = $this->detail('?record_visit=false');

        $this->assertSame(
            $first['data']['total_visits'],
            $second['data']['total_visits'],
            'A prefetch (visit=false) must not increment total_visits'
        );
        $this->assertSame(
            $first['data']['total_visits'],
            $third['data']['total_visits'],
            'The record_visit=false alias must not increment total_visits'
        );
    }

    public function testDefaultReadRecordsExactlyOneVisit(): void
    {
        // A detail read returns the visit count as of before this request, so
        // the increment from a recording read shows up on the next read.
        [, $base] = $this->detail('?visit=false');   // no record
        $this->detail();                             // default: records a visit
        [, $after] = $this->detail('?visit=false');  // observe the increment

        $this->assertSame(
            $base['data']['total_visits'] + 1,
            $after['data']['total_visits'],
            'A normal GET /books/{id} must record exactly one visit'
        );
    }
}
