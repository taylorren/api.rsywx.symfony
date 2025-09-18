<?php

namespace Tests\Integration\WordPress;

use Tests\BaseTestCase;
use App\WordPress\Config\WordPressConfig;

class WordPressConfigIntegrationTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up minimal WordPress configuration for testing
        $_ENV['WP_API_ENABLED'] = 'true';
        $_ENV['WP_API_ENDPOINT'] = 'https://demo.wp-api.org';
        $_ENV['WP_API_USERNAME'] = 'demo';
        $_ENV['WP_API_PASSWORD'] = 'demo';
        $_ENV['WP_API_TIMEOUT'] = '10';
        
        // Database disabled for this test
        $_ENV['WP_DB_ENABLED'] = 'false';
        
        // Cache settings
        $_ENV['WP_CACHE_ENABLED'] = 'true';
        $_ENV['WP_CACHE_TTL_POSTS'] = '1800';
        
        // Site settings
        $_ENV['WP_SITE_ID'] = 'demo';
        $_ENV['WP_SITE_NAME'] = 'Demo WordPress Site';
    }

    public function testWordPressConfigurationLoading(): void
    {
        $config = WordPressConfig::getInstance();
        
        // Test basic configuration loading
        $this->assertTrue($config->isApiEnabled());
        $this->assertFalse($config->isDatabaseEnabled());
        $this->assertEquals('demo', $config->getSiteId());
        $this->assertEquals('Demo WordPress Site', $config->getSiteName());
        
        // Test API configuration
        $this->assertEquals('https://demo.wp-api.org/wp-json/wp/v2', $config->getApiEndpoint());
        
        $credentials = $config->getApiCredentials();
        $this->assertEquals('demo', $credentials['username']);
        $this->assertEquals('demo', $credentials['password']);
        $this->assertEquals(10, $credentials['timeout']);
        
        // Test cache configuration
        $cacheSettings = $config->getCacheSettings();
        $this->assertTrue($cacheSettings['enabled']);
        $this->assertEquals(1800, $cacheSettings['ttl_posts']);
    }

    public function testApiConnectionTest(): void
    {
        $config = WordPressConfig::getInstance();
        
        // Test API connection (this will make a real HTTP request to demo site)
        $result = $config->testApiConnection();
        
        // The demo site might not always be available, so we check for reasonable responses
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        
        if ($result['success']) {
            $this->assertArrayHasKey('details', $result);
            $this->assertArrayHasKey('endpoint', $result['details']);
            $this->assertEquals('https://demo.wp-api.org/wp-json/wp/v2', $result['details']['endpoint']);
        } else {
            // If connection fails, ensure we get proper error information
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('details', $result);
        }
    }

    public function testDatabaseConnectionTestWhenDisabled(): void
    {
        $config = WordPressConfig::getInstance();
        
        // Test database connection when disabled
        $result = $config->testDatabaseConnection();
        
        $this->assertFalse($result['success']);
        $this->assertEquals('WordPress database access is not enabled', $result['error']);
    }

    public function testConfigurationValidation(): void
    {
        $config = WordPressConfig::getInstance();
        
        // Test that configuration is valid
        $fullConfig = $config->getConfig();
        
        $this->assertIsArray($fullConfig);
        $this->assertArrayHasKey('api', $fullConfig);
        $this->assertArrayHasKey('database', $fullConfig);
        $this->assertArrayHasKey('cache', $fullConfig);
        $this->assertArrayHasKey('site', $fullConfig);
        
        // Verify API config structure
        $this->assertArrayHasKey('endpoint', $fullConfig['api']);
        $this->assertArrayHasKey('enabled', $fullConfig['api']);
        $this->assertTrue($fullConfig['api']['enabled']);
        
        // Verify database config structure
        $this->assertArrayHasKey('enabled', $fullConfig['database']);
        $this->assertFalse($fullConfig['database']['enabled']);
        
        // Verify cache config structure
        $this->assertArrayHasKey('enabled', $fullConfig['cache']);
        $this->assertArrayHasKey('ttl_posts', $fullConfig['cache']);
        $this->assertArrayHasKey('ttl_users', $fullConfig['cache']);
        $this->assertArrayHasKey('ttl_taxonomies', $fullConfig['cache']);
    }
}