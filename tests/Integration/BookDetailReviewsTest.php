<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

/**
 * Locks the embedded book-detail `reviews` contract: a minimal, separate schema
 * (`id`, `title`, `datein`, `uri`, `feature`) — NOT the reading-list shape. The
 * review's own image is `feature` (a full URL, possibly different from the
 * book cover), and the link is `uri`.
 */
final class BookDetailReviewsTest extends BaseTestCase
{
    /** Book known to have reviews. */
    private const BOOK_WITH_REVIEWS = '01732';   // 银河帝国——基地

    /** Book known to have no reviews. */
    private const BOOK_WITHOUT_REVIEWS = '02093'; // 姑妄言（上）

    /**
     * @return array{0:int, 1:array}
     */
    private function detail(string $bookid): array
    {
        $request = $this->createRequest('GET', '/api/v1/books/' . $bookid, [
            'X-API-Key' => 'test-api-key-12345',
        ]);
        $response = $this->runApp($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    public function testReviewsHaveTheAgreedSchema(): void
    {
        [$status, $body] = $this->detail(self::BOOK_WITH_REVIEWS);

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('reviews', $body['data']);
        $reviews = $body['data']['reviews'];
        $this->assertIsArray($reviews);
        $this->assertNotEmpty($reviews, 'this book should have reviews');

        foreach ($reviews as $review) {
            $this->assertArrayHasKey('id', $review);
            $this->assertArrayHasKey('title', $review);
            $this->assertArrayHasKey('datein', $review);
            $this->assertArrayHasKey('uri', $review);
            $this->assertArrayHasKey('feature', $review);

            $this->assertIsInt($review['id']);
            $this->assertIsString($review['title']);
            // Review link and review image must be usable URLs.
            $this->assertStringStartsWith('http', (string) $review['uri']);
            $this->assertTrue(
                $review['feature'] === null || str_starts_with((string) $review['feature'], 'http'),
                'feature must be null or an absolute URL'
            );
        }
    }

    public function testBookWithoutReviewsReturnsEmptyArray(): void
    {
        [$status, $body] = $this->detail(self::BOOK_WITHOUT_REVIEWS);

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('reviews', $body['data']);
        $this->assertSame([], $body['data']['reviews']);
    }
}
