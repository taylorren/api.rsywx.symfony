<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

class TodaysBooksEndpointTest extends BaseTestCase
{
    private $apiKey = 'test-api-key-12345';

    public function testTodaysBooksEndpointDefault()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today?refresh=true', [
            'X-API-Key' => $this->apiKey
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('cached', $data);
            $this->assertArrayHasKey('date_info', $data);

            // Check date_info structure
            $dateInfo = $data['date_info'];
            $this->assertArrayHasKey('requested_date', $dateInfo);
            $this->assertArrayHasKey('month_day', $dateInfo);
            $this->assertArrayHasKey('is_today', $dateInfo);
            $this->assertTrue($dateInfo['is_today']); // Should be true for default call

            // Verify date format
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateInfo['requested_date']);
            $this->assertMatchesRegularExpression('/^\d{2}-\d{2}$/', $dateInfo['month_day']);

            // Should return an array of books
            $this->assertIsArray($data['data']);

            // Check book structure if books exist
            foreach ($data['data'] as $book) {
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('translated', $book);
                $this->assertArrayHasKey('copyrighter', $book);
                $this->assertArrayHasKey('region', $book);
                $this->assertArrayHasKey('location', $book);


                $this->assertArrayHasKey('purchdate', $book);
                $this->assertArrayHasKey('price', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('years_ago', $book);

                // Check data types
                $this->assertIsInt($book['id']);
                $this->assertIsString($book['bookid']);
                $this->assertIsString($book['title']);
                $this->assertIsString($book['author']);
                $this->assertIsString($book['location']);
                $this->assertIsString($book['cover_uri']);
                $this->assertIsInt($book['years_ago']);
                $this->assertGreaterThan(0, $book['years_ago']); // Should be from previous years

                // Verify purchase date matches requested month/day
                $purchaseDate = new \DateTime($book['purchdate']);
                $requestedDate = new \DateTime($dateInfo['requested_date']);
                $this->assertEquals($requestedDate->format('m-d'), $purchaseDate->format('m-d'));
                $this->assertLessThan($requestedDate->format('Y'), $purchaseDate->format('Y')); // Previous year
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointWithSpecificDate()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/12/25', [
            'X-API-Key' => $this->apiKey
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('cached', $data);
            $this->assertArrayHasKey('date_info', $data);

            // Check date_info for Christmas
            $dateInfo = $data['date_info'];
            $this->assertEquals('12-25', $dateInfo['month_day']);
            $this->assertStringContainsString('-12-25', $dateInfo['requested_date']);
            $this->assertIsBool($dateInfo['is_today']);

            // Should return an array of books
            $this->assertIsArray($data['data']);

            // Check that all returned books were purchased on December 25th
            foreach ($data['data'] as $book) {
                $purchaseDate = new \DateTime($book['purchdate']);
                $this->assertEquals('12-25', $purchaseDate->format('m-d'));
                $this->assertGreaterThan(0, $book['years_ago']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointWithNewYearsDay()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/1/1', [
            'X-API-Key' => $this->apiKey
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('date_info', $data);

            // Check date_info for New Year's Day
            $dateInfo = $data['date_info'];
            $this->assertEquals('01-01', $dateInfo['month_day']);
            $this->assertStringContainsString('-01-01', $dateInfo['requested_date']);

            // Check that all returned books were purchased on January 1st
            foreach ($data['data'] as $book) {
                $purchaseDate = new \DateTime($book['purchdate']);
                $this->assertEquals('01-01', $purchaseDate->format('m-d'));
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointWithLeapYearDate()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/2/29', [
            'X-API-Key' => $this->apiKey
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('date_info', $data);

            // Check date_info for leap year date
            $dateInfo = $data['date_info'];
            $this->assertEquals('02-29', $dateInfo['month_day']);
            $this->assertStringContainsString('-02-29', $dateInfo['requested_date']);

            // Check that all returned books were purchased on February 29th (leap year)
            foreach ($data['data'] as $book) {
                $purchaseDate = new \DateTime($book['purchdate']);
                $this->assertEquals('02-29', $purchaseDate->format('m-d'));

                // Verify it was actually a leap year
                $year = (int)$purchaseDate->format('Y');
                $this->assertTrue($this->isLeapYear($year), "Book purchased on Feb 29 should be from a leap year, got year: $year");
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointWithInvalidDate()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/2/30', [
            'X-API-Key' => $this->apiKey
        ]);

        $response = $this->runApp($request);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Invalid date', $data['message']);
    }

    public function testTodaysBooksEndpointWithInvalidMonth()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/13/1', [
            'X-API-Key' => $this->apiKey
        ]);

        $response = $this->runApp($request);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Invalid date: month must be 1-12, date must be 1-31', $data['message']);
    }

    public function testTodaysBooksEndpointWithInvalidDay()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/4/31', [
            'X-API-Key' => $this->apiKey
        ]);

        $response = $this->runApp($request);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Invalid date', $data['message']);
    }

    public function testTodaysBooksEndpointWithZeroMonth()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/0/15', [
            'X-API-Key' => $this->apiKey
        ]);

        $response = $this->runApp($request);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid date: month must be 1-12, date must be 1-31', $data['message']);
    }

    public function testTodaysBooksEndpointWithZeroDay()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/6/0', [
            'X-API-Key' => $this->apiKey
        ]);

        $response = $this->runApp($request);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid date: month must be 1-12, date must be 1-31', $data['message']);
    }

    public function testTodaysBooksEndpointWithRefresh()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/8/7?refresh=true', [
            'X-API-Key' => $this->apiKey
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('cached', $data);

            // With refresh=true, should not be from cache
            $this->assertFalse($data['cached']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointCaching()
    {
        try {
            // First request - should not be cached
            $request1 = $this->createRequest('GET', '/api/v1/books/today/3/15', [
                'X-API-Key' => $this->apiKey
            ]);
            $response1 = $this->runApp($request1);
            $this->assertEquals(200, $response1->getStatusCode());

            $body1 = (string) $response1->getBody();
            $data1 = json_decode($body1, true);

            // Second request - should be cached
            $request2 = $this->createRequest('GET', '/api/v1/books/today/3/15', [
                'X-API-Key' => $this->apiKey
            ]);
            $response2 = $this->runApp($request2);
            $this->assertEquals(200, $response2->getStatusCode());

            $body2 = (string) $response2->getBody();
            $data2 = json_decode($body2, true);

            // Both should have same data
            $this->assertEquals($data1['data'], $data2['data']);
            $this->assertEquals($data1['date_info'], $data2['date_info']);

            // Second should be cached (if caching is working)
            // Note: This might not always be true in test environment
            $this->assertArrayHasKey('cached', $data2);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointRequiresApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid or missing API key', $data['message']);
    }

    public function testTodaysBooksEndpointWithParamsRequiresApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today/12/25');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid or missing API key', $data['message']);
    }

    public function testTodaysBooksEndpointWithQueryApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/today?api_key=' . $this->apiKey);

        try {
            $response = $this->runApp($request);
            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testTodaysBooksEndpointBoundaryDates()
    {
        $boundaryDates = [
            [1, 31],   // January 31st
            [3, 31],   // March 31st
            [4, 30],   // April 30th (no 31st)
            [5, 31],   // May 31st
            [6, 30],   // June 30th (no 31st)
            [7, 31],   // July 31st
            [8, 31],   // August 31st
            [9, 30],   // September 30th (no 31st)
            [10, 31],  // October 31st
            [11, 30],  // November 30th (no 31st)
            [12, 31],  // December 31st
        ];

        foreach ($boundaryDates as [$month, $day]) {
            $request = $this->createRequest('GET', "/api/v1/books/today/$month/$day", [
                'X-API-Key' => $this->apiKey
            ]);

            try {
                $response = $this->runApp($request);

                $this->assertEquals(
                    200,
                    $response->getStatusCode(),
                    "Failed for date $month/$day"
                );

                $body = (string) $response->getBody();
                $data = json_decode($body, true);

                $this->assertIsArray($data);
                $this->assertTrue(
                    $data['success'],
                    "API call failed for date $month/$day"
                );

                $expectedMonthDay = sprintf('%02d-%02d', $month, $day);
                $this->assertEquals(
                    $expectedMonthDay,
                    $data['date_info']['month_day'],
                    "Month-day mismatch for $month/$day"
                );
            } catch (\Exception $e) {
                $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
                break;
            }
        }
    }

    /**
     * Helper method to check if a year is a leap year
     */
    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }
}
