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
     * Test that "id" is NOT a list type: an explicit bookid is a one-or-none
     * lookup served by GET /books/{bookid}, never a general list/search.
     */
    public function testIdIsNotAValidListType()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/id/00666/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid type', $data['message']);
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

    /**
     * Slim already URL-decodes route arguments; the controller must NOT decode
     * again. An encoded '+' (C%2B%2B) must reach the model as the literal
     * string "C++". Regression data: the DB contains titles such as
     * "C++程序设计与应用", so the old double-decode bug (which turned the
     * value into "C  ") returned 0 results while the model returns 5.
     */
    public function testEncodedPlusSearchReachesModelUnchanged()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/title/C%2B%2B/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertTrue($data['success']);

        $model = new \App\Models\Book();
        $expected = $model->listBooks('title', 'C++', 1);

        $this->assertSame(
            $expected['pagination']['total_results'],
            $data['pagination']['total_results'],
            'Encoded C%2B%2B must search for the literal "C++", not the double-decoded "C  "'
        );
        $this->assertSame(5, $data['pagination']['total_results']);
        foreach ($data['data'] as $book) {
            $this->assertStringContainsString('C++', $book['title']);
        }
    }

    /**
     * KNOWN TRANSPORT LIMITATION (INT-02): an encoded slash (%2F) inside a
     * path segment fails Slim route matching (404) before the controller
     * runs, so the double-decode fix cannot help here. Resolving this
     * requires a coordinated transport change (e.g. a query parameter for
     * the search value). This test pins the current behavior so any
     * accidental change is noticed.
     */
    public function testEncodedSlashIsAKnownTransportLimitation()
    {
        $request = $this->createRequest('GET', '/api/v1/books/list/title/a%2Fb/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Encoded spaces (and Chinese text) must survive transport unchanged:
     * the endpoint results must equal a direct model search for the decoded
     * value, not the double-decoded value.
     */
    public function testEncodedSpacesAndChineseReachModelUnchanged()
    {
        // Encoded spaces: must reach the model as the literal "A S Hornby"
        // (real data — 1 matching author in the DB).
        $request = $this->createRequest('GET', '/api/v1/books/list/author/A%20S%20Hornby/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertTrue($data['success']);

        $model = new \App\Models\Book();
        $expected = $model->listBooks('author', 'A S Hornby', 1);

        $this->assertSame(
            $expected['pagination']['total_results'],
            $data['pagination']['total_results'],
            'Encoded spaces must produce the same result set as the decoded value'
        );
        $this->assertSame(1, $data['pagination']['total_results']);

        // Chinese text
        $request = $this->createRequest('GET', '/api/v1/books/list/author/' . rawurlencode('章振邦') . '/1', [
            'X-API-Key' => $this->validApiKey
        ]);
        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertTrue($data['success']);

        $expected = $model->listBooks('author', '章振邦', 1);
        $this->assertSame(
            $expected['pagination']['total_results'],
            $data['pagination']['total_results']
        );
    }
}
