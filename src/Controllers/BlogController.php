<?php

namespace App\Controllers;

use App\Cache\ApcuCache;
use App\Exceptions\UpstreamException;
use App\Exceptions\ValidationException;
use App\WordPress\SimpleWordPressDB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

/**
 * Blog Controller
 * Minimal read-only endpoints over the WordPress database:
 * - "on this day in history" posts
 * - latest posts for the frontend index page
 */
class BlogController
{
    private SimpleWordPressDB $wpdb;
    private ApcuCache $cache;

    public function __construct()
    {
        // Simple env-based config
        try {
            $this->wpdb = new SimpleWordPressDB(
                $_ENV['WP_DB_HOST'] ?? 'localhost',
                $_ENV['WP_DB_NAME'] ?? 'wordpress',
                $_ENV['WP_DB_USER'] ?? 'wp_user',
                $_ENV['WP_DB_PASSWORD'] ?? '',
                $_ENV['WP_DB_PREFIX'] ?? 'wp_'
            );
        } catch (\Exception $e) {
            throw new UpstreamException('Blog database unavailable', $e->getMessage(), $e);
        }

        $this->cache = new ApcuCache();
    }

    #[OA\Get(
        path: "/blogs/today/{month}/{day}",
        summary: "Get blog posts for specific date",
        description: "Returns blog posts published on a specific date in previous years (excluding current year) - like 'on this day in history' for your blog.",
        tags: ["Blog"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "month",
        in: "path",
        description: "Month (1-12)",
        required: true,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 12, example: 9)
    )]
    #[OA\Parameter(
        name: "day",
        in: "path",
        description: "Day of month (1-31)",
        required: true,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 31, example: 5)
    )]
    #[OA\Response(
        response: 200,
        description: "Blog posts published on the specified date in previous years",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "ID" => new OA\Property(property: "ID", type: "integer", example: 9879),
                            "post_title" => new OA\Property(property: "post_title", type: "string", example: "三个AI做一道小题目"),
                            "post_name" => new OA\Property(property: "post_name", type: "string", example: "three-ai-small-problem"),
                            "post_excerpt" => new OA\Property(property: "post_excerpt", type: "string", example: "Post excerpt..."),
                            "post_date" => new OA\Property(property: "post_date", type: "string", example: "2022-09-05 10:23:28"),
                            "post_status" => new OA\Property(property: "post_status", type: "string", example: "publish"),
                            "author" => new OA\Property(property: "author", type: "string", example: "root"),
                            "years_ago" => new OA\Property(property: "years_ago", type: "integer", example: 2, description: "How many years ago this post was published"),
                            "permalink" => new OA\Property(property: "permalink", type: "string", example: "https://blog.rsywx.net/2022/09/05/three-ai-small-problem/", description: "Full WordPress permalink URL to the post")
                        ]
                    )
                ),
                "date_info" => new OA\Property(
                    property: "date_info",
                    type: "object",
                    properties: [
                        "month" => new OA\Property(property: "month", type: "integer", example: 9),
                        "day" => new OA\Property(property: "day", type: "integer", example: 5),
                        "date_string" => new OA\Property(property: "date_string", type: "string", example: "September 5"),
                        "is_today" => new OA\Property(property: "is_today", type: "boolean", example: true)
                    ]
                ),
                "count" => new OA\Property(property: "count", type: "integer", example: 6)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid date parameters",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: false),
                "message" => new OA\Property(property: "message", type: "string", example: "Invalid date parameters")
            ]
        )
    )]
    public function todayWithParams(Request $request, Response $response, array $args = []): Response
    {
        // Get month and day from URL params
        $month = isset($args['month']) ? (int)$args['month'] : null;
        $day = isset($args['day']) ? (int)$args['day'] : null;

        $this->validateDate($month, $day);

        $queryParams = $request->getQueryParams();
        $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

        $posts = $this->fetchPostsOnThisDay($month, $day, $forceRefresh, $fromCache);

        // Prepare date info
        $currentMonth = $month ?? (int)date('n');
        $currentDay = $day ?? (int)date('j');

        $data = [
            'success' => true,
            'data' => $posts,
            'date_info' => [
                'month' => $currentMonth,
                'day' => $currentDay,
                'date_string' => date('F j', mktime(0, 0, 0, $currentMonth, $currentDay)),
                'is_today' => ($month === null && $day === null)
            ],
            'count' => count($posts),
            'cached' => $fromCache
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Validate month/day arguments; throws 400 ValidationException on bad input.
     */
    private function validateDate(?int $month, ?int $day): void
    {
        if ($month === null || $day === null) {
            return;
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw new ValidationException('Invalid date parameters');
        }

        // Check if the date is valid (handles Feb 30, Apr 31, etc.)
        if (!checkdate($month, $day, 2024)) { // Use 2024 as test year (leap year)
            throw new ValidationException('Invalid date');
        }
    }

    /**
     * Cached wrapper around SimpleWordPressDB::getPostsOnThisDay().
     * Returns [posts, fromCache].
     */
    private function fetchPostsOnThisDay(?int $month, ?int $day, bool $forceRefresh, ?bool &$fromCache): array
    {
        $currentMonth = $month ?? (int)date('n');
        $currentDay = $day ?? (int)date('j');
        $cacheKey = "blogs_on_this_day_{$currentMonth}_{$currentDay}";

        $posts = null;
        if (!$forceRefresh) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                $posts = $cached;
            }
        }

        $fromCache = ($posts !== null);

        if ($posts === null) {
            try {
                $posts = $this->wpdb->getPostsOnThisDay($month, $day);
            } catch (\PDOException $e) {
                throw new UpstreamException('Failed to fetch blog posts', $e->getMessage(), $e);
            }

            $this->cache->set($cacheKey, $posts, 86400); // 24h
        }

        return $posts;
    }

    #[OA\Get(
        path: "/blogs/today",
        summary: "Get blog posts for today in history",
        description: "Returns blog posts published on today's date in previous years (excluding current year) - like 'on this day in history' for your blog.",
        tags: ["Blog"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "refresh",
        in: "query",
        description: "Force refresh cache",
        required: false,
        schema: new OA\Schema(type: "boolean", example: false)
    )]
    #[OA\Response(
        response: 200,
        description: "Blog posts published on today's date in previous years",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "ID" => new OA\Property(property: "ID", type: "integer", example: 9879),
                            "post_title" => new OA\Property(property: "post_title", type: "string", example: "Don't be a ..."),
                            "post_name" => new OA\Property(property: "post_name", type: "string", example: "dont-be-a"),
                            "post_excerpt" => new OA\Property(property: "post_excerpt", type: "string", example: "Post excerpt..."),
                            "post_date" => new OA\Property(property: "post_date", type: "string", example: "2022-09-05 10:23:28"),
                            "post_status" => new OA\Property(property: "post_status", type: "string", example: "publish"),
                            "author" => new OA\Property(property: "author", type: "string", example: "root"),
                            "years_ago" => new OA\Property(property: "years_ago", type: "integer", example: 2, description: "How many years ago this post was published"),
                            "permalink" => new OA\Property(property: "permalink", type: "string", example: "/2022/09/05/dont-be-a/", description: "WordPress permalink to the post")
                        ]
                    )
                ),
                "date_info" => new OA\Property(
                    property: "date_info",
                    type: "object",
                    properties: [
                        "month" => new OA\Property(property: "month", type: "integer", example: 9),
                        "day" => new OA\Property(property: "day", type: "integer", example: 5),
                        "date_string" => new OA\Property(property: "date_string", type: "string", example: "September 5"),
                        "is_today" => new OA\Property(property: "is_today", type: "boolean", example: true)
                    ]
                ),
                "count" => new OA\Property(property: "count", type: "integer", example: 6),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function today(Request $request, Response $response): Response
    {
        // This is just an alias for todayWithParams with no parameters
        return $this->todayWithParams($request, $response);
    }

    #[OA\Get(
        path: "/blogs/latest/{count}",
        summary: "Get latest blog posts",
        description: "Returns the most recently published blog posts, ordered by date (newest first) - for the frontend index page.",
        tags: ["Blog"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of posts to return (defaults to 3)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50, example: 3)
    )]
    #[OA\Parameter(
        name: "refresh",
        in: "query",
        description: "Force refresh cache",
        required: false,
        schema: new OA\Schema(type: "boolean", example: false)
    )]
    #[OA\Response(
        response: 200,
        description: "Latest blog posts",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "ID" => new OA\Property(property: "ID", type: "integer", example: 9879),
                            "post_title" => new OA\Property(property: "post_title", type: "string", example: "三个AI做一道小题目"),
                            "post_name" => new OA\Property(property: "post_name", type: "string", example: "three-ai-small-problem"),
                            "post_excerpt" => new OA\Property(property: "post_excerpt", type: "string", example: "Post excerpt (falls back to the first 150 characters of the content)..."),
                            "post_date" => new OA\Property(property: "post_date", type: "string", example: "2025-09-01 10:30:00"),
                            "post_status" => new OA\Property(property: "post_status", type: "string", example: "publish"),
                            "author" => new OA\Property(property: "author", type: "string", example: "root"),
                            "permalink" => new OA\Property(property: "permalink", type: "string", example: "https://blog.rsywx.net/2025/09/01/three-ai-small-problem/", description: "Full WordPress permalink URL to the post")
                        ]
                    )
                ),
                "count" => new OA\Property(property: "count", type: "integer", example: 3),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function latest(Request $request, Response $response, array $args = []): Response
    {
        $count = isset($args['count']) ? (int)$args['count'] : 3;
        $count = max(1, min(50, $count)); // Ensure count is between 1 and 50

        $queryParams = $request->getQueryParams();
        $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

        $cacheKey = "blogs_latest_{$count}";

        $posts = null;
        if (!$forceRefresh) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                $posts = $cached;
            }
        }

        $fromCache = ($posts !== null);

        if ($posts === null) {
            try {
                $posts = $this->wpdb->getLatestPosts($count);
            } catch (\PDOException $e) {
                throw new UpstreamException('Failed to fetch blog posts', $e->getMessage(), $e);
            }

            $this->cache->set($cacheKey, $posts, 3600); // 1h - index page freshness
        }

        $data = [
            'success' => true,
            'data' => $posts,
            'count' => count($posts),
            'cached' => $fromCache
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
