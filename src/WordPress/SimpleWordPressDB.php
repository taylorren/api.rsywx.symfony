<?php

namespace App\WordPress;

use PDO;
use PDOException;

/**
 * Simple WordPress Database Client
 * Direct database access without interfaces, configs, or abstractions
 */
class SimpleWordPressDB
{
    private PDO $pdo;
    private string $prefix;

    public function __construct(string $host, string $dbname, string $username, string $password, string $prefix = 'wp_')
    {
        $this->prefix = $prefix;
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new \Exception("WordPress DB connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get posts published on this day in history
     * Similar to the book API's "today's books" functionality
     */
    public function getPostsOnThisDay(?int $month = null, ?int $day = null): array
    {
        // Use current date if not specified
        if ($month === null || $day === null) {
            $month = (int)date('n');
            $day = (int)date('j');
        }

        // Get posts from previous years on this exact day
        // Exclude post_content (too long) and add post_name for permalink
        $sql = "SELECT p.ID, p.post_title, p.post_name, p.post_excerpt, 
                       p.post_date, p.post_status, u.display_name as author,
                       YEAR(CURDATE()) - YEAR(p.post_date) as years_ago
                FROM {$this->prefix}posts p 
                LEFT JOIN {$this->prefix}users u ON p.post_author = u.ID 
                WHERE p.post_status = 'publish' 
                AND p.post_type = 'post'
                AND MONTH(p.post_date) = ?
                AND DAY(p.post_date) = ?
                AND YEAR(p.post_date) < YEAR(CURDATE())
                ORDER BY p.post_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$month, $day]);
        $posts = $stmt->fetchAll();
        
        // Add permalink to each post
        $blogUrl = $_ENV['WP_BLOG_URL'] ?? 'https://blog.rsywx.net';
        foreach ($posts as &$post) {
            // Generate WordPress permalink: https://blog.rsywx.net/YYYY/MM/DD/post-name/
            $date = new \DateTime($post['post_date']);
            $post['permalink'] = sprintf(
                '%s/%04d/%02d/%02d/%s/',
                rtrim($blogUrl, '/'),
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
                $post['post_name']
            );
        }
        
        return $posts;
    }

    /**
     * Raw query for custom needs
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}