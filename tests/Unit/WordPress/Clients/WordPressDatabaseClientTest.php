<?php

namespace Tests\Unit\WordPress\Clients;

use App\WordPress\Clients\WordPressDatabaseClient;
use App\WordPress\Config\WordPressConfigInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PDO;
use PDOStatement;
use Exception;

class WordPressDatabaseClientTest extends TestCase
{
    private WordPressDatabaseClient $client;
    private MockObject $mockConfig;
    private MockObject $mockPdo;
    private MockObject $mockStatement;

    protected function setUp(): void
    {
        $this->mockConfig = $this->createMock(WordPressConfigInterface::class);
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStatement = $this->createMock(PDOStatement::class);

        // Configure mock config
        $this->mockConfig->method('isDatabaseEnabled')->willReturn(true);
        $this->mockConfig->method('getDatabaseConfig')->willReturn([
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'wordpress_test',
            'user' => 'test_user',
            'password' => 'test_pass',
            'charset' => 'utf8mb4',
            'prefix' => 'wp_'
        ]);

        // Create a partial mock that doesn't call the real constructor
        $this->client = $this->getMockBuilder(WordPressDatabaseClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Use reflection to inject dependencies using the actual class
        $reflection = new \ReflectionClass(WordPressDatabaseClient::class);
        
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($this->client, $this->mockConfig);
        
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($this->client, $this->mockPdo);

        $prefixProperty = $reflection->getProperty('tablePrefix');
        $prefixProperty->setAccessible(true);
        $prefixProperty->setValue($this->client, 'wp_');
    }

    public function testConstructorThrowsExceptionWhenDatabaseDisabled(): void
    {
        $mockConfig = $this->createMock(WordPressConfigInterface::class);
        $mockConfig->method('isDatabaseEnabled')->willReturn(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('WordPress database access is not enabled');

        new WordPressDatabaseClient($mockConfig);
    }

    public function testGetPostsWithDefaultCriteria(): void
    {
        $expectedData = [
            [
                'ID' => 1,
                'post_title' => 'Test Post',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'post',
                'author_name' => 'Test Author',
                'author_email' => 'test@example.com'
            ]
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT p.*, u.display_name as author_name'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with([])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedData);

        $result = $this->client->getPosts();

        $this->assertEquals($expectedData, $result);
    }

    public function testGetPostsWithCustomCriteria(): void
    {
        $criteria = [
            'post_type' => 'page',
            'post_status' => 'draft',
            'author_id' => 2,
            'date_from' => '2023-01-01',
            'date_to' => '2023-12-31',
            'order_by' => 'post_title',
            'order' => 'ASC',
            'limit' => 10,
            'offset' => 5
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND p.post_type = :post_type'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) use ($criteria) {
                return $params['post_type'] === 'page' &&
                       $params['post_status'] === 'draft' &&
                       $params['author_id'] === 2 &&
                       $params['date_from'] === '2023-01-01' &&
                       $params['date_to'] === '2023-12-31' &&
                       $params['limit'] === 10 &&
                       $params['offset'] === 5;
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->client->getPosts($criteria);
    }

    public function testGetPostReturnsPostWhenFound(): void
    {
        $expectedPost = [
            'ID' => 1,
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
            'author_name' => 'Test Author'
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE p.ID = :id'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedPost);

        $result = $this->client->getPost(1);

        $this->assertEquals($expectedPost, $result);
    }

    public function testGetPostReturnsNullWhenNotFound(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = $this->client->getPost(999);

        $this->assertNull($result);
    }

    public function testGetUsersWithDefaultCriteria(): void
    {
        $expectedData = [
            [
                'ID' => 1,
                'user_login' => 'testuser',
                'user_email' => 'test@example.com',
                'display_name' => 'Test User',
                'capabilities' => 'a:1:{s:10:"subscriber";b:1;}',
                'first_name' => 'Test',
                'last_name' => 'User'
            ]
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT u.*'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedData);

        $result = $this->client->getUsers();

        $this->assertEquals($expectedData, $result);
    }

    public function testGetUsersWithRoleFilter(): void
    {
        $criteria = ['role' => 'administrator'];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains("AND um.meta_key = 'wp_capabilities' AND um.meta_value LIKE :role"))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return $params['role'] === '%administrator%';
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->client->getUsers($criteria);
    }

    public function testGetUsersWithSearchFilter(): void
    {
        $criteria = ['search' => 'john'];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND (u.user_login LIKE :search OR u.user_email LIKE :search OR u.display_name LIKE :search)'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return $params['search'] === '%john%';
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->client->getUsers($criteria);
    }

    public function testGetUser(): void
    {
        $expectedUser = [
            'ID' => 1,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com'
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE ID = :id'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedUser);

        $result = $this->client->getUser(1);

        $this->assertEquals($expectedUser, $result);
    }

    public function testGetPostMetaAllKeys(): void
    {
        $expectedMeta = [
            ['meta_key' => '_thumbnail_id', 'meta_value' => '123'],
            ['meta_key' => 'custom_field', 'meta_value' => 'custom_value']
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE post_id = :post_id'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['post_id' => 1])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedMeta);

        $result = $this->client->getPostMeta(1);

        $expected = [
            '_thumbnail_id' => '123',
            'custom_field' => 'custom_value'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetPostMetaSpecificKey(): void
    {
        $expectedMeta = [
            ['meta_key' => '_thumbnail_id', 'meta_value' => '123']
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND meta_key = :meta_key'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['post_id' => 1, 'meta_key' => '_thumbnail_id'])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedMeta);

        $result = $this->client->getPostMeta(1, '_thumbnail_id');

        $expected = ['_thumbnail_id' => '123'];

        $this->assertEquals($expected, $result);
    }

    public function testGetUserMeta(): void
    {
        $expectedMeta = [
            ['meta_key' => 'first_name', 'meta_value' => 'John'],
            ['meta_key' => 'last_name', 'meta_value' => 'Doe']
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE user_id = :user_id'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['user_id' => 1])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedMeta);

        $result = $this->client->getUserMeta(1);

        $expected = [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $this->assertEquals($expected, $result);
    }   
 public function testGetTerms(): void
    {
        $expectedTerms = [
            [
                'term_id' => 1,
                'name' => 'Technology',
                'slug' => 'technology',
                'taxonomy' => 'category',
                'description' => 'Tech posts',
                'parent' => 0,
                'count' => 5
            ]
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE tt.taxonomy = :taxonomy'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['taxonomy' => 'category'])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedTerms);

        $result = $this->client->getTerms('category');

        $this->assertEquals($expectedTerms, $result);
    }

    public function testGetTermsWithCriteria(): void
    {
        $criteria = [
            'parent' => 1,
            'search' => 'tech',
            'hide_empty' => true,
            'order_by' => 'count',
            'order' => 'DESC',
            'limit' => 5,
            'offset' => 10
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalAnd(
                $this->stringContains('AND tt.parent = :parent'),
                $this->stringContains('AND (t.name LIKE :search OR t.slug LIKE :search)'),
                $this->stringContains('AND tt.count > 0'),
                $this->stringContains('ORDER BY t.count DESC'),
                $this->stringContains('LIMIT :limit')
            ))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return isset($params['taxonomy']) && $params['taxonomy'] === 'category' &&
                       isset($params['parent']) && $params['parent'] === 1 &&
                       isset($params['search']) && $params['search'] === '%tech%' &&
                       isset($params['limit']) && $params['limit'] === 5 &&
                       isset($params['offset']) && $params['offset'] === 10;
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->client->getTerms('category', $criteria);
    }

    public function testGetTermRelationships(): void
    {
        $expectedRelationships = [
            [
                'term_id' => 1,
                'name' => 'Technology',
                'slug' => 'technology',
                'taxonomy' => 'category'
            ],
            [
                'term_id' => 2,
                'name' => 'PHP',
                'slug' => 'php',
                'taxonomy' => 'post_tag'
            ]
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE tr.object_id = :object_id'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['object_id' => 1])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedRelationships);

        $result = $this->client->getTermRelationships(1);

        $this->assertEquals($expectedRelationships, $result);
    }

    public function testGetTermRelationshipsWithTaxonomy(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND tt.taxonomy = :taxonomy'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with(['object_id' => 1, 'taxonomy' => 'category'])
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->client->getTermRelationships(1, 'category');
    }

    public function testSearchPosts(): void
    {
        $expectedPosts = [
            [
                'ID' => 1,
                'post_title' => 'WordPress Tutorial',
                'post_content' => 'Learn WordPress development',
                'author_name' => 'John Doe'
            ]
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE (p.post_title LIKE :query OR p.post_content LIKE :query OR p.post_excerpt LIKE :query)'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return $params['query'] === '%wordpress%';
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedPosts);

        $result = $this->client->searchPosts('wordpress');

        $this->assertEquals($expectedPosts, $result);
    }

    public function testSearchPostsWithCriteria(): void
    {
        $criteria = [
            'post_type' => 'page',
            'post_status' => 'draft',
            'author_id' => 2,
            'limit' => 10,
            'offset' => 5
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalAnd(
                $this->stringContains('AND p.post_type = :post_type'),
                $this->stringContains('AND p.post_status = :post_status'),
                $this->stringContains('AND p.post_author = :author_id'),
                $this->stringContains('LIMIT :limit'),
                $this->stringContains('OFFSET :offset')
            ))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) use ($criteria) {
                return $params['query'] === '%test%' &&
                       $params['post_type'] === 'page' &&
                       $params['post_status'] === 'draft' &&
                       $params['author_id'] === 2 &&
                       $params['limit'] === 10 &&
                       $params['offset'] === 5;
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->client->searchPosts('test', $criteria);
    }

    public function testGetPostsByTaxonomy(): void
    {
        $expectedPosts = [
            [
                'ID' => 1,
                'post_title' => 'Tech Post',
                'post_content' => 'Technology content',
                'author_name' => 'Tech Author'
            ]
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('WHERE tt.taxonomy = :taxonomy AND tt.term_id = :term_id'))
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) {
                return $params['taxonomy'] === 'category' &&
                       $params['term_id'] === 1;
            }))
            ->willReturn(true);

        $this->mockStatement->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedPosts);

        $result = $this->client->getPostsByTaxonomy('category', 1);

        $this->assertEquals($expectedPosts, $result);
    }

    public function testGetPostsWithTaxonomies(): void
    {
        $posts = [
            ['ID' => 1, 'post_title' => 'Test Post'],
            ['ID' => 2, 'post_title' => 'Another Post']
        ];

        $taxonomies1 = [
            ['term_id' => 1, 'name' => 'Category 1', 'taxonomy' => 'category']
        ];

        $taxonomies2 = [
            ['term_id' => 2, 'name' => 'Tag 1', 'taxonomy' => 'post_tag']
        ];

        // Mock the getPosts call
        $client = $this->getMockBuilder(WordPressDatabaseClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPosts', 'getTermRelationships'])
            ->getMock();

        $client->expects($this->once())
            ->method('getPosts')
            ->with([])
            ->willReturn($posts);

        $client->expects($this->exactly(2))
            ->method('getTermRelationships')
            ->withConsecutive([1], [2])
            ->willReturnOnConsecutiveCalls($taxonomies1, $taxonomies2);

        $result = $client->getPostsWithTaxonomies();

        $expected = [
            ['ID' => 1, 'post_title' => 'Test Post', 'taxonomies' => $taxonomies1],
            ['ID' => 2, 'post_title' => 'Another Post', 'taxonomies' => $taxonomies2]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetConnection(): void
    {
        $result = $this->client->getConnection();
        $this->assertSame($this->mockPdo, $result);
    }

    public function testGetTablePrefix(): void
    {
        $result = $this->client->getTablePrefix();
        $this->assertEquals('wp_', $result);
    }

    public function testTestConnectionSuccess(): void
    {
        // Mock successful connection test
        $this->mockPdo->expects($this->once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($this->mockStatement);

        // Mock table existence checks
        $this->mockPdo->expects($this->exactly(7))
            ->method('prepare')
            ->with('SHOW TABLES LIKE ?')
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->exactly(7))
            ->method('execute')
            ->willReturn(true);

        $this->mockStatement->expects($this->exactly(7))
            ->method('rowCount')
            ->willReturn(1);

        $result = $this->client->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('WordPress database connection and structure verified', $result['message']);
        $this->assertEquals('wp_', $result['details']['prefix']);
        $this->assertEquals(7, $result['details']['tables_verified']);
    }

    public function testTestConnectionMissingTables(): void
    {
        // Mock successful connection test
        $this->mockPdo->expects($this->once())
            ->method('query')
            ->with('SELECT 1')
            ->willReturn($this->mockStatement);

        // Mock table existence checks - some tables missing
        $this->mockPdo->expects($this->exactly(7))
            ->method('prepare')
            ->with('SHOW TABLES LIKE ?')
            ->willReturn($this->mockStatement);

        $this->mockStatement->expects($this->exactly(7))
            ->method('execute')
            ->willReturn(true);

        // First 3 tables exist, last 4 don't
        $this->mockStatement->expects($this->exactly(7))
            ->method('rowCount')
            ->willReturnOnConsecutiveCalls(1, 1, 1, 0, 0, 0, 0);

        $result = $this->client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertEquals('Missing WordPress tables', $result['error']);
        $this->assertCount(4, $result['details']['missing_tables']);
    }

    public function testExceptionHandling(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Database error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to retrieve posts: Database error');

        $this->client->getPosts();
    }
}