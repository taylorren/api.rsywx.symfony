<?php

namespace Tests\Unit\WordPress\Models;

use App\WordPress\Models\WordPressPost;
use App\WordPress\Models\WordPressUser;
use DateTime;
use PHPUnit\Framework\TestCase;

class WordPressPostTest extends TestCase
{
    public function testPostCreationFromArray()
    {
        $data = [
            'id' => 123,
            'title' => 'Test Post',
            'content' => 'This is test content',
            'excerpt' => 'Test excerpt',
            'status' => 'published',
            'type' => 'post',
            'date' => new DateTime('2024-01-01'),
            'modified' => new DateTime('2024-01-02'),
            'slug' => 'test-post',
            'categories' => [1, 2],
            'tags' => [3, 4],
            'meta' => ['key' => 'value'],
            'featuredImage' => 'image.jpg'
        ];

        $post = new WordPressPost($data);

        $this->assertEquals(123, $post->id);
        $this->assertEquals('Test Post', $post->title);
        $this->assertEquals('This is test content', $post->content);
        $this->assertEquals('Test excerpt', $post->excerpt);
        $this->assertEquals('published', $post->status);
        $this->assertEquals('post', $post->type);
        $this->assertEquals('test-post', $post->slug);
        $this->assertEquals([1, 2], $post->categories);
        $this->assertEquals([3, 4], $post->tags);
        $this->assertEquals(['key' => 'value'], $post->meta);
        $this->assertEquals('image.jpg', $post->featuredImage);
    }

    public function testPostFromApiResponse()
    {
        $apiResponse = [
            'id' => 456,
            'title' => ['rendered' => 'API Post'],
            'content' => ['rendered' => 'API content'],
            'excerpt' => ['rendered' => 'API excerpt'],
            'status' => 'draft',
            'type' => 'page',
            'date' => '2024-01-01T10:00:00',
            'modified' => '2024-01-02T11:00:00',
            'slug' => 'api-post',
            'categories' => [5, 6],
            'tags' => [7, 8],
            'meta' => ['api_key' => 'api_value'],
            'featured_media' => 'api-image.jpg'
        ];

        $post = WordPressPost::fromApiResponse($apiResponse);

        $this->assertEquals(456, $post->id);
        $this->assertEquals('API Post', $post->title);
        $this->assertEquals('API content', $post->content);
        $this->assertEquals('API excerpt', $post->excerpt);
        $this->assertEquals('draft', $post->status);
        $this->assertEquals('page', $post->type);
        $this->assertEquals('api-post', $post->slug);
        $this->assertEquals([5, 6], $post->categories);
        $this->assertEquals([7, 8], $post->tags);
        $this->assertEquals(['api_key' => 'api_value'], $post->meta);
        $this->assertEquals('api-image.jpg', $post->featuredImage);
    }

    public function testPostFromDatabaseRow()
    {
        $dbRow = [
            'ID' => '789',
            'post_title' => 'DB Post',
            'post_content' => 'DB content',
            'post_excerpt' => 'DB excerpt',
            'post_status' => 'published',
            'post_type' => 'post',
            'post_date' => '2024-01-01 10:00:00',
            'post_modified' => '2024-01-02 11:00:00',
            'post_name' => 'db-post'
        ];

        $post = WordPressPost::fromDatabaseRow($dbRow);

        $this->assertEquals(789, $post->id);
        $this->assertEquals('DB Post', $post->title);
        $this->assertEquals('DB content', $post->content);
        $this->assertEquals('DB excerpt', $post->excerpt);
        $this->assertEquals('published', $post->status);
        $this->assertEquals('post', $post->type);
        $this->assertEquals('db-post', $post->slug);
        $this->assertEquals([], $post->categories);
        $this->assertEquals([], $post->tags);
        $this->assertEquals([], $post->meta);
    }

    public function testPostToArray()
    {
        $post = new WordPressPost([
            'id' => 100,
            'title' => 'Array Test',
            'content' => 'Array content',
            'date' => new DateTime('2024-01-01T10:00:00'),
            'modified' => new DateTime('2024-01-02T11:00:00')
        ]);

        $array = $post->toArray();

        $this->assertEquals(100, $array['id']);
        $this->assertEquals('Array Test', $array['title']);
        $this->assertEquals('Array content', $array['content']);
        $this->assertStringContainsString('2024-01-01T10:00:00', $array['date']);
        $this->assertStringContainsString('2024-01-02T11:00:00', $array['modified']);
    }
}