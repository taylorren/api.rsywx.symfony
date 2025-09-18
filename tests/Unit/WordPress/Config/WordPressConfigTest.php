<?php

namespace Tests\Unit\WordPress\Config;

use PHPUnit\Framework\TestCase;
use App\WordPress\Config\WordPressConfig;
use InvalidArgumentException;

class WordPressConfigTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original environment
        $this->originalEnv = $_ENV;
        
        // Clear WordPress-related environment variables
        $this->clearWordPressEnv();
    }

    protected function tearDown(): void
    {
        // Restore original environment
        $_ENV = $this->originalEnv;
        
        // Reset singleton instance using reflection
        $reflection = new \ReflectionClass(WordPressConfig::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null);
        
        parent::tearDown();
    }

    private function clearWordPressEnv(): void
    {
        $wpEnvVars = [
            'WP_API_ENABLED', 'WP_API_ENDPOINT', 'WP_API_USERNAME', 'WP_API_PASSWORD',
            'WP_APPLICATION_PASSWORD', 'WP_JWT_SECRET', 'WP_API_TIMEOUT',
            'WP_DB_ENABLED', 'WP_DB_HOST', 'WP_DB_PORT', 'WP_DB_NAME', 
            'WP_DB_USER', 'WP_DB_PASSWORD', 'WP_DB_CHARSET', 'WP_DB_PREFIX',
            'WP_CACHE_ENABLED', 'WP_CACHE_TTL_POSTS', 'WP_CACHE_TTL_USERS', 'WP_CACHE_TTL_TAXONOMIES',
            'WP_SITE_ID', 'WP_SITE_NAME'
        ];
        
        foreach ($wpEnvVars as $var) {
            unset($_ENV[$var]);
        }
    }

    private function setMinimalApiConfig(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'https://example.com';
        $_ENV['WP_API_USERNAME'] = 'testuser';
        $_ENV['WP_API_PASSWORD'] = 'testpass';
    }

    private function setMinimalDatabaseConfig(): void
    {
        $_ENV['WP_DB_ENABLED'] = 'true';
        $_ENV['WP_DB_HOST'] = 'localhost';
        $_ENV['WP_DB_NAME'] = 'wordpress';
        $_ENV['WP_DB_USER'] = 'wpuser';
        $_ENV['WP_DB_PASSWORD'] = 'wppass';
    }

    public function testSingletonPattern(): void
    {
        $this->setMinimalApiConfig();
        
        $instance1 = WordPressConfig::getInstance();
        $instance2 = WordPressConfig::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    public function testValidApiConfiguration(): void
    {
        $this->setMinimalApiConfig();
        
        $config = WordPressConfig::getInstance();
        
        $this->assertTrue($config->isApiEnabled());
        $this->assertFalse($config->isDatabaseEnabled());
        $this->assertEquals('https://example.com/wp-json/wp/v2', $config->getApiEndpoint());
        
        $credentials = $config->getApiCredentials();
        $this->assertEquals('testuser', $credentials['username']);
        $this->assertEquals('testpass', $credentials['password']);
        $this->assertEquals(30, $credentials['timeout']); // default timeout
    }

    public function testValidDatabaseConfiguration(): void
    {
        $this->setMinimalDatabaseConfig();
        
        $config = WordPressConfig::getInstance();
        
        $this->assertFalse($config->isApiEnabled());
        $this->assertTrue($config->isDatabaseEnabled());
        
        $dbConfig = $config->getDatabaseConfig();
        $this->assertEquals('localhost', $dbConfig['host']);
        $this->assertEquals('wordpress', $dbConfig['name']);
        $this->assertEquals('wpuser', $dbConfig['user']);
        $this->assertEquals('wppass', $dbConfig['password']);
        $this->assertEquals('wp_', $dbConfig['prefix']); // default prefix
        $this->assertEquals(3306, $dbConfig['port']); // default port
    }

    public function testBothApiAndDatabaseEnabled(): void
    {
        $this->setMinimalApiConfig();
        $this->setMinimalDatabaseConfig();
        
        $config = WordPressConfig::getInstance();
        
        $this->assertTrue($config->isApiEnabled());
        $this->assertTrue($config->isDatabaseEnabled());
    }

    public function testNoConnectionMethodEnabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one WordPress connection method (API or Database) must be enabled and configured');
        
        WordPressConfig::getInstance();
    }

    public function testApiEnabledButMissingEndpoint(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_USERNAME'] = 'testuser';
        $_ENV['WP_API_PASSWORD'] = 'testpass';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WordPress API endpoint is required when API is enabled');
        
        WordPressConfig::getInstance();
    }

    public function testApiEnabledButInvalidEndpoint(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'not-a-valid-url';
        $_ENV['WP_API_USERNAME'] = 'testuser';
        $_ENV['WP_API_PASSWORD'] = 'testpass';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WordPress API endpoint must be a valid URL');
        
        WordPressConfig::getInstance();
    }

    public function testApiEnabledButMissingAuthentication(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'https://example.com';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WordPress API requires authentication');
        
        WordPressConfig::getInstance();
    }

    public function testDatabaseEnabledButMissingRequiredFields(): void
    {
        $_ENV['WP_DB_ENABLED'] = 'true';
        $_ENV['WP_DB_HOST'] = 'localhost';
        // Missing name, user, password
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WordPress database name is required when database access is enabled');
        
        WordPressConfig::getInstance();
    }

    public function testCacheSettings(): void
    {
        $this->setMinimalApiConfig();
        $_ENV['WP_CACHE_TTL_POSTS'] = '7200';
        $_ENV['WP_CACHE_TTL_USERS'] = '28800';
        $_ENV['WP_CACHE_TTL_TAXONOMIES'] = '172800';
        
        $config = WordPressConfig::getInstance();
        $cacheSettings = $config->getCacheSettings();
        
        $this->assertEquals(7200, $cacheSettings['ttl_posts']);
        $this->assertEquals(28800, $cacheSettings['ttl_users']);
        $this->assertEquals(172800, $cacheSettings['ttl_taxonomies']);
        $this->assertTrue($cacheSettings['enabled']); // default
    }

    public function testInvalidCacheTtl(): void
    {
        $this->setMinimalApiConfig();
        $_ENV['WP_CACHE_TTL_POSTS'] = '-1';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache ttl_posts must be a non-negative integer');
        
        WordPressConfig::getInstance();
    }

    public function testSiteSettings(): void
    {
        $this->setMinimalApiConfig();
        $_ENV['WP_SITE_ID'] = 'test-site';
        $_ENV['WP_SITE_NAME'] = 'Test WordPress Site';
        
        $config = WordPressConfig::getInstance();
        
        $this->assertEquals('test-site', $config->getSiteId());
        $this->assertEquals('Test WordPress Site', $config->getSiteName());
    }

    public function testDefaultValues(): void
    {
        $this->setMinimalApiConfig();
        
        $config = WordPressConfig::getInstance();
        
        // Test API defaults
        $credentials = $config->getApiCredentials();
        $this->assertEquals(30, $credentials['timeout']);
        
        // Test database defaults
        $dbConfig = $config->getDatabaseConfig();
        $this->assertEquals('utf8mb4', $dbConfig['charset']);
        $this->assertEquals('wp_', $dbConfig['prefix']);
        $this->assertEquals(3306, $dbConfig['port']);
        
        // Test cache defaults
        $cacheSettings = $config->getCacheSettings();
        $this->assertEquals(3600, $cacheSettings['ttl_posts']);
        $this->assertEquals(14400, $cacheSettings['ttl_users']);
        $this->assertEquals(86400, $cacheSettings['ttl_taxonomies']);
        $this->assertTrue($cacheSettings['enabled']);
        
        // Test site defaults
        $this->assertEquals('main', $config->getSiteId());
        $this->assertEquals('WordPress Site', $config->getSiteName());
    }

    public function testApplicationPasswordAuthentication(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'https://example.com';
        $_ENV['WP_API_USERNAME'] = 'testuser';
        $_ENV['WP_APPLICATION_PASSWORD'] = 'app-password-123';
        
        $config = WordPressConfig::getInstance();
        
        $this->assertTrue($config->isApiEnabled());
        
        $credentials = $config->getApiCredentials();
        $this->assertEquals('testuser', $credentials['username']);
        $this->assertEquals('app-password-123', $credentials['application_password']);
    }

    public function testJwtAuthentication(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'https://example.com';
        $_ENV['WP_JWT_SECRET'] = 'jwt-secret-key';
        
        $config = WordPressConfig::getInstance();
        
        $this->assertTrue($config->isApiEnabled());
        
        $credentials = $config->getApiCredentials();
        $this->assertEquals('jwt-secret-key', $credentials['jwt_secret']);
    }

    public function testApiEndpointFormatting(): void
    {
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'https://example.com/';  // with trailing slash
        $_ENV['WP_API_USERNAME'] = 'testuser';
        $_ENV['WP_API_PASSWORD'] = 'testpass';
        
        $config = WordPressConfig::getInstance();
        
        // Should remove trailing slash and add wp-json/wp/v2
        $this->assertEquals('https://example.com/wp-json/wp/v2', $config->getApiEndpoint());
    }
}