<?php

namespace Tests\Unit\WordPress\Utils;

use App\WordPress\Utils\WordPressModelFactory;
use App\WordPress\Models\WordPressPost;
use App\WordPress\Models\WordPressUser;
use App\WordPress\Models\WordPressTaxonomy;
use PHPUnit\Framework\TestCase;

class WordPressModelFactoryTest extends TestCase
{
    public function testCreatePostFromArray()
    {
        $data = [
            'id' => 123,
            'title' => 'Test Post',
            'content' => 'Test content'
        ];

        $post = WordPressModelFactory::createPost($data, 'array');

        $this->assertInstanceOf(WordPressPost::class, $post);
        $this->assertEquals(123, $post->id);
        $this->assertEquals('Test Post', $post->title);
        $this->assertEquals('Test content', $post->content);
    }

    public function testCreatePostFromApi()
    {
        $data = [
            'id' => 456,
            'title' => ['rendered' => 'API Post'],
            'content' => ['rendered' => 'API content'],
            'excerpt' => ['rendered' => 'API excerpt'],
            'status' => 'published'
        ];

        $post = WordPressModelFactory::createPost($data, 'api');

        $this->assertInstanceOf(WordPressPost::class, $post);
        $this->assertEquals(456, $post->id);
        $this->assertEquals('API Post', $post->title);
        $this->assertEquals('API content', $post->content);
        $this->assertEquals('published', $post->status);
    }

    public function testCreatePostFromDatabase()
    {
        $data = [
            'ID' => '789',
            'post_title' => 'DB Post',
            'post_content' => 'DB content',
            'post_status' => 'draft'
        ];

        $post = WordPressModelFactory::createPost($data, 'database');

        $this->assertInstanceOf(WordPressPost::class, $post);
        $this->assertEquals(789, $post->id);
        $this->assertEquals('DB Post', $post->title);
        $this->assertEquals('DB content', $post->content);
        $this->assertEquals('draft', $post->status);
    }

    public function testCreateMultiplePosts()
    {
        $dataArray = [
            ['id' => 1, 'title' => 'Post 1'],
            ['id' => 2, 'title' => 'Post 2'],
            ['id' => 3, 'title' => 'Post 3']
        ];

        $posts = WordPressModelFactory::createPosts($dataArray, 'array');

        $this->assertCount(3, $posts);
        $this->assertInstanceOf(WordPressPost::class, $posts[0]);
        $this->assertInstanceOf(WordPressPost::class, $posts[1]);
        $this->assertInstanceOf(WordPressPost::class, $posts[2]);
        
        $this->assertEquals(1, $posts[0]->id);
        $this->assertEquals('Post 1', $posts[0]->title);
        $this->assertEquals(2, $posts[1]->id);
        $this->assertEquals('Post 2', $posts[1]->title);
        $this->assertEquals(3, $posts[2]->id);
        $this->assertEquals('Post 3', $posts[2]->title);
    }

    public function testCreateUserFromArray()
    {
        $data = [
            'id' => 100,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'displayName' => 'Test User'
        ];

        $user = WordPressModelFactory::createUser($data, 'array');

        $this->assertInstanceOf(WordPressUser::class, $user);
        $this->assertEquals(100, $user->id);
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('Test User', $user->displayName);
    }

    public function testCreateTaxonomyFromArray()
    {
        $data = [
            'id' => 50,
            'name' => 'Test Category',
            'slug' => 'test-category',
            'taxonomy' => 'category'
        ];

        $taxonomy = WordPressModelFactory::createTaxonomy($data, 'array');

        $this->assertInstanceOf(WordPressTaxonomy::class, $taxonomy);
        $this->assertEquals(50, $taxonomy->id);
        $this->assertEquals('Test Category', $taxonomy->name);
        $this->assertEquals('test-category', $taxonomy->slug);
        $this->assertEquals('category', $taxonomy->taxonomy);
    }

    public function testCreateMultipleUsers()
    {
        $dataArray = [
            ['id' => 1, 'username' => 'user1', 'email' => 'user1@example.com'],
            ['id' => 2, 'username' => 'user2', 'email' => 'user2@example.com']
        ];

        $users = WordPressModelFactory::createUsers($dataArray, 'array');

        $this->assertCount(2, $users);
        $this->assertInstanceOf(WordPressUser::class, $users[0]);
        $this->assertInstanceOf(WordPressUser::class, $users[1]);
        
        $this->assertEquals(1, $users[0]->id);
        $this->assertEquals('user1', $users[0]->username);
        $this->assertEquals(2, $users[1]->id);
        $this->assertEquals('user2', $users[1]->username);
    }

    public function testCreateMultipleTaxonomies()
    {
        $dataArray = [
            ['id' => 1, 'name' => 'Category 1', 'taxonomy' => 'category'],
            ['id' => 2, 'name' => 'Tag 1', 'taxonomy' => 'post_tag']
        ];

        $taxonomies = WordPressModelFactory::createTaxonomies($dataArray, 'array');

        $this->assertCount(2, $taxonomies);
        $this->assertInstanceOf(WordPressTaxonomy::class, $taxonomies[0]);
        $this->assertInstanceOf(WordPressTaxonomy::class, $taxonomies[1]);
        
        $this->assertEquals(1, $taxonomies[0]->id);
        $this->assertEquals('Category 1', $taxonomies[0]->name);
        $this->assertEquals('category', $taxonomies[0]->taxonomy);
        $this->assertEquals(2, $taxonomies[1]->id);
        $this->assertEquals('Tag 1', $taxonomies[1]->name);
        $this->assertEquals('post_tag', $taxonomies[1]->taxonomy);
    }
}