<?php

namespace Tests\Unit\WordPress\Clients;

use App\WordPress\Clients\WordPressApiClient;
use App\WordPress\Config\WordPressConfigInterface;
use App\WordPress\Clients\HttpClientInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;

class WordPressApiClientTest extends TestCase
{
    private WordPressApiClient $apiClient;
    private MockObject $mockConfig;
    private MockObject $mockLogger;
    private MockObject $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockConfig = $this->createMock(WordPressConfigInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);

        $this->mockConfig->method('getApiEndpoint')
            ->willReturn('https://example.com');
        
        $this->mockConfig->method('getApiCredentials')
            ->willReturn(['api_key' => 'test-key']);

        $this->apiClient = new WordPressApiClient(
            $this->mockConfig,
            $this->mockLogger,
            $this->mockHttpClient
        );
    }

    public function testConstructorSetsUpAuthenticationWithApiKey(): void
    {
        $this->mockConfig->expects($this->once())
            ->method('getApiCredentials')
            ->willReturn(['api_key' => 'test-api-key']);

        new WordPressApiClient($this->mockConfig, $this->mockLogger);
    }

    public function testConstructorSetsUpAuthenticationWithApplicationPassword(): void
    {
        $this->mockConfig->expects($this->once())
            ->method('getApiCredentials')
            ->willReturn([
                'username' => 'testuser',
                'application_password' => 'test-app-password'
            ]);

        new WordPressApiClient($this->mockConfig, $this->mockLogger);
    }

    public function testConstructorSetsUpAuthenticationWithJwtToken(): void
    {
        $this->mockConfig->expects($this->once())
            ->method('getApiCredentials')
            ->willReturn(['jwt_token' => 'test-jwt-token']);

        new WordPressApiClient($this->mockConfig, $this->mockLogger);
    }

    public function testGetPostsReturnsArrayOfPosts(): void
    {
        $expectedResponse = [
            ['id' => 1, 'title' => ['rendered' => 'Post 1']],
            ['id' => 2, 'title' => ['rendered' => 'Post 2']]
        ];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/posts', $this->anything(), null)
            ->willReturn([
                'body' => json_encode($expectedResponse),
                'http_code' => 200,
                'error' => ''
            ]);

        $result = $this->apiClient->getPosts();

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetPostsWithParametersBuildsQueryString(): void
    {
        $params = ['per_page' => 5, 'status' => 'publish'];
        $expectedResponse = [['id' => 1, 'title' => ['rendered' => 'Post 1']]];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/posts?per_page=5&status=publish', $this->anything(), null)
            ->willReturn([
                'body' => json_encode($expectedResponse),
                'http_code' => 200,
                'error' => ''
            ]);

        $result = $this->apiClient->getPosts($params);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetPostReturnsSpecificPost(): void
    {
        $postId = 123;
        $expectedResponse = ['id' => 123, 'title' => ['rendered' => 'Test Post']];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/posts/123', $this->anything(), null)
            ->willReturn([
                'body' => json_encode($expectedResponse),
                'http_code' => 200,
                'error' => ''
            ]);

        $result = $this->apiClient->getPost($postId);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetPostReturnsNullWhenNotFound(): void
    {
        $postId = 999;

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/posts/999', $this->anything(), null)
            ->willReturn([
                'body' => json_encode(['message' => 'Post not found']),
                'http_code' => 404,
                'error' => ''
            ]);

        $result = $this->apiClient->getPost($postId);

        $this->assertNull($result);
    }

    public function testGetPostThrowsExceptionForInvalidId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post ID must be a positive integer');

        $this->apiClient->getPost(0);
    }

    public function testGetUsersReturnsArrayOfUsers(): void
    {
        $expectedResponse = [
            ['id' => 1, 'name' => 'User 1'],
            ['id' => 2, 'name' => 'User 2']
        ];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/users', $this->anything(), null)
            ->willReturn([
                'body' => json_encode($expectedResponse),
                'http_code' => 200,
                'error' => ''
            ]);

        $result = $this->apiClient->getUsers();

        $this->assertEquals($expectedResponse, $result);
    }

    public function testSearchReturnsSearchResults(): void
    {
        $query = 'test search';
        $expectedResponse = [
            ['id' => 1, 'title' => 'Search Result 1'],
            ['id' => 2, 'title' => 'Search Result 2']
        ];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/search?search=test+search', $this->anything(), null)
            ->willReturn([
                'body' => json_encode($expectedResponse),
                'http_code' => 200,
                'error' => ''
            ]);

        $result = $this->apiClient->search($query);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testSearchThrowsExceptionForEmptyQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $this->apiClient->search('');
    }

    public function testAuthenticateThrowsExceptionForEmptyCredentials(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username and password are required');

        $this->apiClient->authenticate('', 'password');
    }

    public function testAuthenticateReturnsUserDataOnSuccess(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $expectedResponse = ['id' => 1, 'name' => 'Test User'];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/wp-json/wp/v2/users/me', $this->anything(), null)
            ->willReturn([
                'body' => json_encode($expectedResponse),
                'http_code' => 200,
                'error' => ''
            ]);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('WordPress user authenticated successfully', $this->anything());

        $result = $this->apiClient->authenticate($username, $password);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testMakeRequestHandlesInvalidJsonResponse(): void
    {
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'body' => 'invalid json',
                'http_code' => 200,
                'error' => ''
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON response from WordPress API');

        $this->apiClient->getPosts();
    }

    public function testMakeRequestHandlesHttpErrors(): void
    {
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'body' => json_encode(['message' => 'Internal server error']),
                'http_code' => 500,
                'error' => ''
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WordPress API error: Internal server error');

        $this->apiClient->getPosts();
    }

    public function testMakeRequestHandlesConnectionErrors(): void
    {
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn([
                'body' => false,
                'http_code' => 0,
                'error' => 'Connection failed'
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WordPress API connection failed: Connection failed');

        $this->apiClient->getPosts();
    }
}