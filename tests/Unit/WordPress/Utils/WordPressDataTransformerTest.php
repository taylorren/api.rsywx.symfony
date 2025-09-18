<?php

namespace Tests\Unit\WordPress\Utils;

use App\WordPress\Utils\WordPressDataTransformer;
use App\WordPress\Models\WordPressPost;
use App\WordPress\Models\WordPressUser;
use App\WordPress\Models\WordPressTaxonomy;
use PHPUnit\Framework\TestCase;

class WordPressDataTransformerTest extends TestCase
{
    public function testTransformApiPostsResponse()
    {
        $apiResponse = [
            [
                'id' => 1,
                'title' => ['rendered' => 'Post 1'],
                'content' => ['rendered' => 'Content 1'],
                'excerpt' => ['rendered' => 'Excerpt 1'],
                'status' => 'published'
            ],
            [
                'id' => 2,
                'title' => ['rendered' => 'Post 2'],
                'content' => ['rendered' => 'Content 2'],
                'excerpt' => ['rendered' => 'Excerpt 2'],
                'status' => 'draft'
            ]
        ];

        $posts = WordPressDataTransformer::transformApiPostsResponse($apiResponse);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(WordPressPost::class, $posts[0]);
        $this->assertEquals(1, $posts[0]->id);
        $this->assertEquals('Post 1', $posts[0]->title);
        $this->assertEquals('published', $posts[0]->status);
        
        $this->assertInstanceOf(WordPressPost::class, $posts[1]);
        $this->assertEquals(2, $posts[1]->id);
        $this->assertEquals('Post 2', $posts[1]->title);
        $this->assertEquals('draft', $posts[1]->status);
    }

    public function testTransformDatabasePostsResponse()
    {
        $dbResponse = [
            [
                'ID' => '1',
                'post_title' => 'DB Post 1',
                'post_content' => 'DB Content 1',
                'post_status' => 'published'
            ],
            [
                'ID' => '2',
                'post_title' => 'DB Post 2',
                'post_content' => 'DB Content 2',
                'post_status' => 'draft'
            ]
        ];

        $posts = WordPressDataTransformer::transformDatabasePostsResponse($dbResponse);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(WordPressPost::class, $posts[0]);
        $this->assertEquals(1, $posts[0]->id);
        $this->assertEquals('DB Post 1', $posts[0]->title);
        $this->assertEquals('published', $posts[0]->status);
    }

    public function testNormalizePostDataFromApi()
    {
        $apiData = [
            'id' => 123,
            'title' => ['rendered' => 'API Title'],
            'content' => ['rendered' => 'API Content'],
            'excerpt' => ['rendered' => 'API Excerpt'],
            'status' => 'published',
            'author' => 456,
            'categories' => [1, 2, 3]
        ];

        $normalized = WordPressDataTransformer::normalizePostData($apiData, 'api');

        $this->assertEquals(123, $normalized['id']);
        $this->assertEquals('API Title', $normalized['title']);
        $this->assertEquals('API Content', $normalized['content']);
        $this->assertEquals('API Excerpt', $normalized['excerpt']);
        $this->assertEquals('published', $normalized['status']);
        $this->assertEquals(456, $normalized['author_id']);
        $this->assertEquals([1, 2, 3], $normalized['categories']);
    }

    public function testNormalizePostDataFromDatabase()
    {
        $dbData = [
            'ID' => '123',
            'post_title' => 'DB Title',
            'post_content' => 'DB Content',
            'post_excerpt' => 'DB Excerpt',
            'post_status' => 'published',
            'post_author' => '456'
        ];

        $normalized = WordPressDataTransformer::normalizePostData($dbData, 'database');

        $this->assertEquals(123, $normalized['id']);
        $this->assertEquals('DB Title', $normalized['title']);
        $this->assertEquals('DB Content', $normalized['content']);
        $this->assertEquals('DB Excerpt', $normalized['excerpt']);
        $this->assertEquals('published', $normalized['status']);
        $this->assertEquals(456, $normalized['author_id']);
        $this->assertEquals([], $normalized['categories']);
    }

    public function testBuildTaxonomyHierarchy()
    {
        $taxonomies = [
            new WordPressTaxonomy(['id' => 1, 'name' => 'Parent 1', 'parent' => null]),
            new WordPressTaxonomy(['id' => 2, 'name' => 'Child 1', 'parent' => 1]),
            new WordPressTaxonomy(['id' => 3, 'name' => 'Child 2', 'parent' => 1]),
            new WordPressTaxonomy(['id' => 4, 'name' => 'Parent 2', 'parent' => null]),
            new WordPressTaxonomy(['id' => 5, 'name' => 'Grandchild', 'parent' => 2])
        ];

        $hierarchy = WordPressDataTransformer::buildTaxonomyHierarchy($taxonomies);

        // Should have 2 root elements
        $this->assertCount(2, $hierarchy);
        
        // First root should have 2 children
        $this->assertEquals('Parent 1', $hierarchy[0]->name);
        $this->assertCount(2, $hierarchy[0]->children);
        
        // First child should have 1 grandchild
        $this->assertEquals('Child 1', $hierarchy[0]->children[0]->name);
        $this->assertCount(1, $hierarchy[0]->children[0]->children);
        $this->assertEquals('Grandchild', $hierarchy[0]->children[0]->children[0]->name);
    }

    public function testParseMetaData()
    {
        $metaData = [
            ['meta_key' => 'key1', 'meta_value' => 'value1'],
            ['meta_key' => 'key2', 'meta_value' => 'value2'],
            ['key' => 'key3', 'value' => 'value3']
        ];

        $parsed = WordPressDataTransformer::parseMetaData($metaData);

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ], $parsed);
    }

    public function testFormatDate()
    {
        $dateTime = new \DateTime('2024-01-01T10:00:00');
        $formatted = WordPressDataTransformer::formatDate($dateTime);
        $this->assertStringContainsString('2024-01-01T10:00:00', $formatted);

        $dateString = '2024-01-01 10:00:00';
        $formatted = WordPressDataTransformer::formatDate($dateString);
        $this->assertStringContainsString('2024-01-01T10:00:00', $formatted);

        $null = WordPressDataTransformer::formatDate(null);
        $this->assertNull($null);

        $invalid = WordPressDataTransformer::formatDate('invalid-date');
        $this->assertNull($invalid);
    }
}