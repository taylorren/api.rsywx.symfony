<?php

namespace Tests\Integration\WordPress;

use App\WordPress\Clients\WordPressApiClient;
use App\WordPress\Config\WordPressConfigInterface;
use App\WordPress\Clients\CurlHttpClient;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WordPressApiClientIntegrationTest extends TestCase
{
    private WordPressApiClient $apiClient;
    private MockObject $mockConfig;
    private MockObject $mockLogger;

    protected function setUp(): void
    {
        $this->mockConfig = $this->createMock(WordPressConfigInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        // Use a test WordPress site endpoint
        $this->mockConfig->method('getApiEndpoint')
            ->willReturn('https://demo.wp-api.org');
        
        $this->mockConfig->method('getApiCredentials')
            ->willReturn([]);

        // Use real HTTP client for integration test
        $this->apiClient = new WordPressApiClient(
            $this->mockConfig,
            $this->mockLogger,
            new CurlHttpClient()
        );
    }

    public function testCurlHttpClientCanMakeRealRequest(): void
    {
        $httpClient = new CurlHttpClient();
        
        $result = $httpClient->request(
            'GET',
            'https://demo.wp-api.org/wp-json/wp/v2/posts?per_page=1',
            ['Accept' => 'application/json']
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('http_code', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(200, $result['http_code']);
        $this->assertEmpty($result['error']);
        
        $data = json_decode($result['body'], true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    /**
     * @group external
     */
    public function testGetPostsFromRealWordPressSite(): void
    {
        // This test requires internet connection and working WordPress demo site
        // Skip if we can't reach the demo site
        if (!$this->canReachDemoSite()) {
            $this->markTestSkipped('Cannot reach WordPress demo site');
        }

        $posts = $this->apiClient->getPosts(['per_page' => 2]);

        $this->assertIsArray($posts);
        $this->assertNotEmpty($posts);
        $this->assertArrayHasKey('id', $posts[0]);
        $this->assertArrayHasKey('title', $posts[0]);
    }

    private function canReachDemoSite(): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://demo.wp-api.org/wp-json/wp/v2/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $httpCode === 200;
    }
}