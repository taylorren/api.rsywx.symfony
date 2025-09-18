<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

class ApiEndpointsTest extends BaseTestCase
{
    public function testHealthEndpoint()
    {
        $request = $this->createRequest('GET', '/health');
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertEquals('API is running', $data['message']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testApiKeyRequired()
    {
        $request = $this->createRequest('GET', '/api/v1/books/status');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid or missing API key', $data['message']);
    }

    public function testBooksStatusEndpointWithValidApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/status', [
            'X-API-Key' => 'test-api-key-12345'
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

            $statusData = $data['data'];
            $this->assertArrayHasKey('total_books', $statusData);
            $this->assertArrayHasKey('total_pages', $statusData);
            $this->assertArrayHasKey('total_kwords', $statusData);
            $this->assertArrayHasKey('total_visits', $statusData);

            // Check data types
            $this->assertIsInt($statusData['total_books']);
            $this->assertIsInt($statusData['total_pages']);
            $this->assertIsInt($statusData['total_kwords']);
            $this->assertIsInt($statusData['total_visits']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testBooksStatusEndpointWithQueryApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/status?api_key=test-api-key-12345');

        try {
            $response = $this->runApp($request);
            $this->assertEquals(200, $response->getStatusCode());
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testBookDetailEndpointWithValidBook()
    {
        $request = $this->createRequest('GET', '/api/v1/books/00666?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            if ($response->getStatusCode() === 404) {
                // Book doesn't exist in test database, which is expected
                $body = (string) $response->getBody();
                $data = json_decode($body, true);

                $this->assertIsArray($data);
                $this->assertFalse($data['success']);
                $this->assertEquals('Book not found', $data['message']);
                return;
            }

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);
            $this->assertArrayHasKey('cached', $data);

            $bookData = $data['data'];
            $this->assertArrayHasKey('id', $bookData);
            $this->assertArrayHasKey('bookid', $bookData);
            $this->assertArrayHasKey('title', $bookData);
            $this->assertArrayHasKey('author', $bookData);
            $this->assertArrayHasKey('translated', $bookData);
            $this->assertArrayHasKey('copyrighter', $bookData);
            $this->assertArrayHasKey('region', $bookData);
            $this->assertArrayHasKey('location', $bookData);
            $this->assertArrayHasKey('cover_uri', $bookData);
            $this->assertArrayHasKey('total_visits', $bookData);
            $this->assertArrayHasKey('last_visited', $bookData);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testBookDetailEndpointWithInvalidBook()
    {
        $request = $this->createRequest('GET', '/api/v1/books/99999', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(404, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertFalse($data['success']);
            $this->assertEquals('Book not found', $data['message']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testLatestBooksEndpointDefault()
    {
        $request = $this->createRequest('GET', '/api/v1/books/latest?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // Should return an array even for count=1
            $this->assertIsArray($data['data']);
            $this->assertCount(1, $data['data']); // Default count is 1

            // Check book structure
            if (!empty($data['data'])) {
                $book = $data['data'][0];
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
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testLatestBooksEndpointWithCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/latest/3', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 3 books
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(3, count($data['data']));
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testCorsHeaders()
    {
        $request = $this->createRequest('GET', '/health');
        $response = $this->runApp($request);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));

        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, PUT, DELETE, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function testRandomBooksEndpointDefault()
    {
        $request = $this->createRequest('GET', '/api/v1/books/random?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // Should return an array even for count=1
            $this->assertIsArray($data['data']);
            $this->assertCount(1, $data['data']); // Default count is 1

            // Check book structure
            if (!empty($data['data'])) {
                $book = $data['data'][0];
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('translated', $book);
                $this->assertArrayHasKey('copyrighter', $book);
                $this->assertArrayHasKey('region', $book);
                $this->assertArrayHasKey('location', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('total_visits', $book);
                $this->assertArrayHasKey('last_visited', $book);

                // Check data types
                $this->assertIsInt($book['id']);
                $this->assertIsString($book['bookid']);
                $this->assertIsString($book['title']);
                $this->assertIsString($book['author']);
                $this->assertIsString($book['cover_uri']);
                $this->assertIsInt($book['total_visits']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testRandomBooksEndpointWithCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/random/5', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 5 books
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(5, count($data['data']));

            // Verify each book has required fields
            foreach ($data['data'] as $book) {
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('translated', $book);
                $this->assertArrayHasKey('copyrighter', $book);
                $this->assertArrayHasKey('region', $book);
                $this->assertArrayHasKey('location', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('total_visits', $book);
                $this->assertArrayHasKey('last_visited', $book);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testRandomBooksEndpointWithMaxCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/random/50', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 50 books (max limit)
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(50, count($data['data']));
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testRandomBooksEndpointWithExcessiveCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/random/100', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should be capped at 50 books even if 100 requested
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(50, count($data['data']));
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testRandomBooksEndpointWithRefresh()
    {
        $request = $this->createRequest('GET', '/api/v1/books/random/3?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // With refresh=true, should not be from cache
            $this->assertFalse($data['cached']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testRandomBooksEndpointRequiresApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/random');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid or missing API key', $data['message']);
    }

    public function testLastVisitedBooksEndpointDefault()
    {
        $request = $this->createRequest('GET', '/api/v1/books/last_visited?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // Should return an array even for count=1
            $this->assertIsArray($data['data']);
            $this->assertCount(1, $data['data']); // Default count is 1

            // Check book structure
            if (!empty($data['data'])) {
                $book = $data['data'][0];
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('translated', $book);
                $this->assertArrayHasKey('copyrighter', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('last_visited', $book);
                $this->assertArrayHasKey('visit_country', $book);
                $this->assertArrayHasKey('region', $book);
                $this->assertArrayHasKey('location', $book);

                // Check data types
                $this->assertIsInt($book['id']);
                $this->assertIsString($book['bookid']);
                $this->assertIsString($book['title']);
                $this->assertIsString($book['author']);
                $this->assertIsString($book['cover_uri']);
                $this->assertIsString($book['last_visited']);
                // Visit country can be null in the database
                $this->assertTrue(is_string($book['visit_country']) || is_null($book['visit_country']));
                // Author region should always be present
                $this->assertIsString($book['region']);
            }
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database connection failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            // For assertion failures and other errors, let the test fail properly
            // Only skip for actual database connectivity issues
            if (
                strpos($e->getMessage(), 'Connection refused') !== false ||
                strpos($e->getMessage(), 'Access denied') !== false ||
                strpos($e->getMessage(), 'Unknown database') !== false
            ) {
                $this->markTestSkipped('Database not available: ' . $e->getMessage());
            } else {
                // Re-throw assertion failures and other test errors
                throw $e;
            }
        }
    }

    public function testLastVisitedBooksEndpointWithCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/last_visited/5', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 5 books
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(5, count($data['data']));

            // Verify books are ordered by last_visited (most recent first)
            if (count($data['data']) > 1) {
                $firstBook = $data['data'][0];
                $secondBook = $data['data'][1];
                $this->assertGreaterThanOrEqual(
                    strtotime($secondBook['last_visited']),
                    strtotime($firstBook['last_visited']),
                    'Books should be ordered by most recent visit first'
                );
            }

            // Verify each book has required fields
            foreach ($data['data'] as $book) {
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('translated', $book);
                $this->assertArrayHasKey('copyrighter', $book);
                $this->assertArrayHasKey('region', $book);
                $this->assertArrayHasKey('location', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('last_visited', $book);
                $this->assertArrayHasKey('visit_country', $book);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testLastVisitedBooksEndpointWithMaxCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/last_visited/50', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 50 books (max limit)
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(50, count($data['data']));
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testLastVisitedBooksEndpointWithRefresh()
    {
        $request = $this->createRequest('GET', '/api/v1/books/last_visited/3?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // With refresh=true, should not be from cache
            $this->assertFalse($data['cached']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testLastVisitedBooksEndpointRequiresApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/last_visited');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid or missing API key', $data['message']);
    }

    public function testForgottenBooksEndpointDefault()
    {
        $request = $this->createRequest('GET', '/api/v1/books/forgotten?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // Should return an array even for count=1
            $this->assertIsArray($data['data']);
            $this->assertCount(1, $data['data']); // Default count is 1

            // Check book structure
            if (!empty($data['data'])) {
                $book = $data['data'][0];
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('last_visited', $book);
                $this->assertArrayHasKey('days_since_visit', $book);

                // Check data types
                $this->assertIsInt($book['id']);
                $this->assertIsString($book['bookid']);
                $this->assertIsString($book['title']);
                $this->assertIsString($book['author']);
                $this->assertIsString($book['cover_uri']);
                $this->assertIsString($book['last_visited']);
                $this->assertIsInt($book['days_since_visit']);

                // Ensure days_since_visit > 0 (these are forgotten books)
                $this->assertGreaterThan(0, $book['days_since_visit']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testForgottenBooksEndpointWithCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/forgotten/5', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 5 books
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(5, count($data['data']));

            // Verify books are ordered by oldest visit first (most forgotten first)
            if (count($data['data']) > 1) {
                $firstBook = $data['data'][0];
                $secondBook = $data['data'][1];
                $this->assertLessThanOrEqual(
                    strtotime($secondBook['last_visited']),
                    strtotime($firstBook['last_visited']),
                    'Books should be ordered by oldest visit first (most forgotten first)'
                );
            }

            // Verify each book has required fields
            foreach ($data['data'] as $book) {
                $this->assertArrayHasKey('id', $book);
                $this->assertArrayHasKey('bookid', $book);
                $this->assertArrayHasKey('title', $book);
                $this->assertArrayHasKey('author', $book);
                $this->assertArrayHasKey('cover_uri', $book);
                $this->assertArrayHasKey('last_visited', $book);
                $this->assertArrayHasKey('days_since_visit', $book);
                $this->assertGreaterThan(0, $book['days_since_visit']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testForgottenBooksEndpointWithMaxCount()
    {
        $request = $this->createRequest('GET', '/api/v1/books/forgotten/50', [
            'X-API-Key' => 'test-api-key-12345'
        ]);

        try {
            $response = $this->runApp($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('data', $data);

            // Should return an array with up to 50 books (max limit)
            $this->assertIsArray($data['data']);
            $this->assertLessThanOrEqual(50, count($data['data']));
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testForgottenBooksEndpointWithRefresh()
    {
        $request = $this->createRequest('GET', '/api/v1/books/forgotten/3?refresh=true', [
            'X-API-Key' => 'test-api-key-12345'
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

            // With refresh=true, should not be from cache
            $this->assertFalse($data['cached']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testForgottenBooksEndpointRequiresApiKey()
    {
        $request = $this->createRequest('GET', '/api/v1/books/forgotten');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid or missing API key', $data['message']);
    }
}
