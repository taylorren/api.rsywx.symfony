<?php

namespace App\Controllers;

use App\WordPress\SimpleWordPressDB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

/**
 * WordPress Controller
 * Direct database access without layers of abstraction
 */
class WordPressController
{
    private SimpleWordPressDB $wpdb;

    public function __construct()
    {
        // Simple env-based config
        $this->wpdb = new SimpleWordPressDB(
            $_ENV['WP_DB_HOST'] ?? 'localhost',
            $_ENV['WP_DB_NAME'] ?? 'wordpress',
            $_ENV['WP_DB_USER'] ?? 'wp_user',
            $_ENV['WP_DB_PASSWORD'] ?? '',
            $_ENV['WP_DB_PREFIX'] ?? 'wp_'
        );
    }

    #[OA\Get(
        path: "/wp/posts/today/{month}/{day}",
        summary: "Get WordPress posts for specific date",
        description: "Returns WordPress posts published on a specific date in previous years (excluding current year) - like 'on this day in history' for your WordPress blog.",
        tags: ["WordPress"],
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
        description: "WordPress posts published on the specified date in previous years",
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
    public function getPostsOnThisDay(Request $request, Response $response, array $args = []): Response
    {
        // Get month and day from URL params or use today's date
        $month = isset($args['month']) ? (int)$args['month'] : null;
        $day = isset($args['day']) ? (int)$args['day'] : null;

        // Validate date if provided
        if ($month !== null && $day !== null) {
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                $data = [
                    'success' => false,
                    'message' => 'Invalid date parameters'
                ];
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Check if the date is valid (handles Feb 30, Apr 31, etc.)
            if (!checkdate($month, $day, 2024)) { // Use 2024 as test year (leap year)
                $data = [
                    'success' => false,
                    'message' => 'Invalid date'
                ];
                $response->getBody()->write(json_encode($data));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        try {
            $posts = $this->wpdb->getPostsOnThisDay($month, $day);

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
                'count' => count($posts)
            ];
        } catch (\Exception $e) {
            $data = [
                'success' => false,
                'message' => 'Failed to fetch WordPress posts',
                'error' => $e->getMessage()
            ];
        }

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    #[OA\Get(
        path: "/wp/posts/today",
        summary: "Get WordPress posts for today in history",
        description: "Returns WordPress posts published on today's date in previous years (excluding current year) - like 'on this day in history' for your WordPress blog.",
        tags: ["WordPress"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Response(
        response: 200,
        description: "WordPress posts published on today's date in previous years",
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
                "count" => new OA\Property(property: "count", type: "integer", example: 6)
            ]
        )
    )]
    public function getTodaysPosts(Request $request, Response $response): Response
    {
        // This is just an alias for getPostsOnThisDay with no parameters
        return $this->getPostsOnThisDay($request, $response);
    }
}
