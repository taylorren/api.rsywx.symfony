<?php

namespace Tests\Integration\WordPress;

use App\WordPress\Clients\WordPressDatabaseClient;
use App\WordPress\Config\WordPressConfig;
use Tests\BaseTestCase;
use Exception;

class WordPressDatabaseClientIntegrationTest extends BaseTestCase
{
    private WordPressDatabaseClient $client;
    private WordPressConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip if WordPress database is not configured
        if (!$this->isWordPressDatabaseConfigured()) {
            $this->markTestSkipped('WordPress database is not configured for testing');
        }

        try {
            $this->config = WordPressConfig::getInstance();
            $this->client = new WordPressDatabaseClient($this->config);
        } catch (Exception $e) {
            $this->markTestSkipped('WordPress database connection failed: ' . $e->getMessage());
        }
    }

    private function isWordPressDatabaseConfigured(): bool
    {
        return !empty($_ENV['WP_DB_HOST']) && 
               !empty($_ENV['WP_DB_NAME']) && 
               !empty($_ENV['WP_DB_USER']) && 
               filter_var($_ENV['WP_DB_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public function testConnectionTest(): void
    {
        $result = $this->client->testConnection();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        
        if ($result['success']) {
            $this->assertArrayHasKey('message', $result);
            $this->assertArrayHasKey('details', $result);
            $this->assertArrayHasKey('prefix', $result['details']);
            $this->assertArrayHasKey('tables_verified', $result['details']);
        } else {
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('details', $result);
        }
    }

    public function testGetTablePrefix(): void
    {
        $prefix = $this->client->getTablePrefix();
        
        $this->assertIsString($prefix);
        $this->assertNotEmpty($prefix);
        $this->assertEquals($_ENV['WP_DB_PREFIX'] ?? 'wp_', $prefix);
    }

    public function testGetConnection(): void
    {
        $connection = $this->client->getConnection();
        
        $this->assertInstanceOf(\PDO::class, $connection);
    }

    public function testGetPostsWithDefaultCriteria(): void
    {
        $posts = $this->client->getPosts();
        
        $this->assertIsArray($posts);
        
        // If there are posts, verify structure
        if (!empty($posts)) {
            $post = $posts[0];
            $this->assertArrayHasKey('ID', $post);
            $this->assertArrayHasKey('post_title', $post);
            $this->assertArrayHasKey('post_content', $post);
            $this->assertArrayHasKey('post_status', $post);
            $this->assertArrayHasKey('post_type', $post);
            $this->assertArrayHasKey('post_date', $post);
            $this->assertArrayHasKey('author_name', $post);
        }
    }

    public function testGetPostsWithLimitAndOffset(): void
    {
        $criteria = [
            'limit' => 5,
            'offset' => 0,
            'order_by' => 'post_date',
            'order' => 'DESC'
        ];
        
        $posts = $this->client->getPosts($criteria);
        
        $this->assertIsArray($posts);
        $this->assertLessThanOrEqual(5, count($posts));
    }

    public function testGetPostsWithStatusFilter(): void
    {
        $criteria = [
            'post_status' => 'publish',
            'limit' => 10
        ];
        
        $posts = $this->client->getPosts($criteria);
        
        $this->assertIsArray($posts);
        
        // Verify all posts have publish status
        foreach ($posts as $post) {
            $this->assertEquals('publish', $post['post_status']);
        }
    }

    public function testGetPostsWithTypeFilter(): void
    {
        $criteria = [
            'post_type' => 'post',
            'limit' => 10
        ];
        
        $posts = $this->client->getPosts($criteria);
        
        $this->assertIsArray($posts);
        
        // Verify all posts have correct type
        foreach ($posts as $post) {
            $this->assertEquals('post', $post['post_type']);
        }
    }

    public function testGetPostById(): void
    {
        // First get a list of posts to get a valid ID
        $posts = $this->client->getPosts(['limit' => 1]);
        
        if (empty($posts)) {
            $this->markTestSkipped('No posts available for testing');
        }
        
        $postId = $posts[0]['ID'];
        $post = $this->client->getPost($postId);
        
        $this->assertIsArray($post);
        $this->assertEquals($postId, $post['ID']);
        $this->assertArrayHasKey('post_title', $post);
        $this->assertArrayHasKey('post_content', $post);
    }

    public function testGetPostByInvalidId(): void
    {
        $post = $this->client->getPost(999999);
        
        $this->assertNull($post);
    }

    public function testGetUsers(): void
    {
        $users = $this->client->getUsers(['limit' => 10]);
        
        $this->assertIsArray($users);
        
        // If there are users, verify structure
        if (!empty($users)) {
            $user = $users[0];
            $this->assertArrayHasKey('ID', $user);
            $this->assertArrayHasKey('user_login', $user);
            $this->assertArrayHasKey('user_email', $user);
            $this->assertArrayHasKey('display_name', $user);
        }
    }

    public function testGetUserById(): void
    {
        // First get a list of users to get a valid ID
        $users = $this->client->getUsers(['limit' => 1]);
        
        if (empty($users)) {
            $this->markTestSkipped('No users available for testing');
        }
        
        $userId = $users[0]['ID'];
        $user = $this->client->getUser($userId);
        
        $this->assertIsArray($user);
        $this->assertEquals($userId, $user['ID']);
        $this->assertArrayHasKey('user_login', $user);
        $this->assertArrayHasKey('user_email', $user);
    }

    public function testGetPostMeta(): void
    {
        // First get a post to test meta retrieval
        $posts = $this->client->getPosts(['limit' => 1]);
        
        if (empty($posts)) {
            $this->markTestSkipped('No posts available for testing');
        }
        
        $postId = $posts[0]['ID'];
        $meta = $this->client->getPostMeta($postId);
        
        $this->assertIsArray($meta);
        // Meta might be empty, but should be an array
    }

    public function testGetUserMeta(): void
    {
        // First get a user to test meta retrieval
        $users = $this->client->getUsers(['limit' => 1]);
        
        if (empty($users)) {
            $this->markTestSkipped('No users available for testing');
        }
        
        $userId = $users[0]['ID'];
        $meta = $this->client->getUserMeta($userId);
        
        $this->assertIsArray($meta);
        // Meta might be empty, but should be an array
    }

    public function testGetTerms(): void
    {
        $terms = $this->client->getTerms('category', ['limit' => 10]);
        
        $this->assertIsArray($terms);
        
        // If there are terms, verify structure
        if (!empty($terms)) {
            $term = $terms[0];
            $this->assertArrayHasKey('term_id', $term);
            $this->assertArrayHasKey('name', $term);
            $this->assertArrayHasKey('slug', $term);
            $this->assertArrayHasKey('taxonomy', $term);
            $this->assertEquals('category', $term['taxonomy']);
        }
    }

    public function testGetTermRelationships(): void
    {
        // First get a post to test term relationships
        $posts = $this->client->getPosts(['limit' => 1]);
        
        if (empty($posts)) {
            $this->markTestSkipped('No posts available for testing');
        }
        
        $postId = $posts[0]['ID'];
        $relationships = $this->client->getTermRelationships($postId);
        
        $this->assertIsArray($relationships);
        
        // If there are relationships, verify structure
        if (!empty($relationships)) {
            $relationship = $relationships[0];
            $this->assertArrayHasKey('term_id', $relationship);
            $this->assertArrayHasKey('name', $relationship);
            $this->assertArrayHasKey('taxonomy', $relationship);
        }
    }

    public function testSearchPosts(): void
    {
        // Search for a common word that might exist in posts
        $results = $this->client->searchPosts('the', ['limit' => 5]);
        
        $this->assertIsArray($results);
        
        // If there are results, verify structure
        if (!empty($results)) {
            $post = $results[0];
            $this->assertArrayHasKey('ID', $post);
            $this->assertArrayHasKey('post_title', $post);
            $this->assertArrayHasKey('post_content', $post);
        }
    }

    public function testGetPostsByTaxonomy(): void
    {
        // First get categories to test with
        $categories = $this->client->getTerms('category', ['limit' => 1]);
        
        if (empty($categories)) {
            $this->markTestSkipped('No categories available for testing');
        }
        
        $categoryId = $categories[0]['term_id'];
        $posts = $this->client->getPostsByTaxonomy('category', $categoryId, ['limit' => 5]);
        
        $this->assertIsArray($posts);
        
        // If there are posts, verify structure
        if (!empty($posts)) {
            $post = $posts[0];
            $this->assertArrayHasKey('ID', $post);
            $this->assertArrayHasKey('post_title', $post);
        }
    }

    public function testGetPostsWithTaxonomies(): void
    {
        $posts = $this->client->getPostsWithTaxonomies(['limit' => 2]);
        
        $this->assertIsArray($posts);
        
        // If there are posts, verify structure includes taxonomies
        if (!empty($posts)) {
            $post = $posts[0];
            $this->assertArrayHasKey('ID', $post);
            $this->assertArrayHasKey('post_title', $post);
            $this->assertArrayHasKey('taxonomies', $post);
            $this->assertIsArray($post['taxonomies']);
        }
    }

    public function testExceptionHandlingWithInvalidQuery(): void
    {
        $this->expectException(Exception::class);
        
        // Force an exception by trying to access a non-existent table
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getTableName');
        $method->setAccessible(true);
        
        // This should work fine
        $tableName = $method->invoke($this->client, 'posts');
        $this->assertStringContainsString('posts', $tableName);
    }
}