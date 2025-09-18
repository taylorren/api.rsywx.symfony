<?php

namespace Tests\Integration;

use Tests\BaseTestCase;

class BookListEndpointTest extends BaseTestCase
{
    /**
     * @var string Valid API key for testing
     */
    private $validApiKey = 'test-api-key-12345';

    /**
     * Test default listing (no parameters)
     */
    public function testDefaultBookListing()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertNotEmpty($body, "Response body should not be empty");
        
        $data = json_decode($body, true);
        $this->assertNotNull($data, "Response should be valid JSON");
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    /**
     * Test listing with explicit wildcard
     */
    public function testWildcardListing()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/title/-/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertNotEmpty($body, "Response body should not be empty");
        
        $data = json_decode($body, true);
        $this->assertNotNull($data, "Response should be valid JSON");
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertGreaterThan(0, count($data['data']), "Should return at least one book");
    }

    /**
     * Test title search
     */
    public function testTitleSearch()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/title/Harry/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        foreach ($data['data'] as $book) {
            $this->assertStringContainsStringIgnoringCase('Harry', $book['title']);
        }
    }

    /**
     * Test author search
     */
    public function testAuthorSearch()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/author/Rowling/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        foreach ($data['data'] as $book) {
            $this->assertStringContainsStringIgnoringCase('Rowling', $book['author']);
        }
    }

    /**
     * Test tags search
     */
    public function testTagsSearch()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/tags/fiction/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test misc search
     */
    public function testMiscSearch()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/misc/test/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test invalid type
     */
    public function testInvalidType()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/invalid/test/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid type', $data['message']);
    }

    /**
     * Test pagination
     */
    public function testPagination()
    {
        // Test with full path
        $request = $this->createRequest('GET', '/api/v1/books/list/title/-/2', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(2, $data['pagination']['current_page']);

        // Test with shorthand page number notation
        $request = $this->createRequest('GET', '/api/v1/books/list/2', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(2, $data['pagination']['current_page']);
    }

    /**
     * Test API key requirement
     */
    public function testApiKeyRequired()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list');
        $response = $this->runApp($request);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('API key', $data['message']);
    }
}
