<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class ReadingController
{
    #[OA\Get(
        path: "/readings/summary",
        summary: "Get Reading Summary Statistics",
        description: "Returns reading statistics including books read count, reviews written count, and reading activity date range",
        tags: ["Reading Statistics"],
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
        description: "Reading summary statistics",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "books_read" => new OA\Property(property: "books_read", type: "integer", example: 42),
                        "reviews_written" => new OA\Property(property: "reviews_written", type: "integer", example: 156),
                        "reading_period" => new OA\Property(
                            property: "reading_period",
                            type: "object",
                            properties: [
                                "earliest_date" => new OA\Property(property: "earliest_date", type: "string", example: "2020-01-15"),
                                "latest_date" => new OA\Property(property: "latest_date", type: "string", example: "2025-07-28"),
                                "total_days" => new OA\Property(property: "total_days", type: "integer", example: 1825)
                            ]
                        )
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function summary(Request $request, Response $response)
    {
        try {
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';
            
            $readingModel = new \App\Models\Reading();
            $result = $readingModel->getReadingSummary($forceRefresh);
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: "/readings/latest/{count}",
        summary: "Get Latest Readings",
        description: "Returns the most recent reading activities with book details, ordered by reading date (newest first)",
        tags: ["Reading Statistics"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of latest readings to return (defaults to 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50, example: 1)
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
        description: "Latest reading activities",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "title" => new OA\Property(property: "title", type: "string", example: "My Review Title"),
                            "datein" => new OA\Property(property: "datein", type: "string", example: "2025-07-14"),
                            "uri" => new OA\Property(property: "uri", type: "string", example: "/reviews/my-review"),
                            "feature" => new OA\Property(property: "feature", type: "string", example: "feature-image.jpg"),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "book_title" => new OA\Property(property: "book_title", type: "string", example: "Book Being Reviewed"),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function latest(Request $request, Response $response, $args)
    {
        try {
            $count = isset($args['count']) ? (int)$args['count'] : 1;
            $count = max(1, min(50, $count)); // Ensure count is between 1 and 50
            
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';
            
            $readingModel = new \App\Models\Reading();
            $result = $readingModel->getLatestReadings($count, $forceRefresh);
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: "/readings/reviews/{page}",
        summary: "Get Reviews with Pagination",
        description: "Returns reviews with pagination support, ordered by date (newest first). Fixed at 9 reviews per page for consistent frontend display.",
        tags: ["Reading Statistics"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "page",
        in: "path",
        description: "Page number (defaults to 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, example: 1)
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
        description: "Paginated reviews",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "title" => new OA\Property(property: "title", type: "string", example: "My Review Title"),
                            "datein" => new OA\Property(property: "datein", type: "string", example: "2025-07-14"),
                            "uri" => new OA\Property(property: "uri", type: "string", example: "https://blog.rsywx.net/2025/07/14/my-review/"),
                            "feature" => new OA\Property(property: "feature", type: "string", example: "https://blog.rsywx.net/wp-content/uploads/2025/07/feature.jpg"),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "book_title" => new OA\Property(property: "book_title", type: "string", example: "Book Being Reviewed"),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg")
                        ]
                    )
                ),
                "pagination" => new OA\Property(
                    property: "pagination",
                    type: "object",
                    properties: [
                        "current_page" => new OA\Property(property: "current_page", type: "integer", example: 1),
                        "total_pages" => new OA\Property(property: "total_pages", type: "integer", example: 5),
                        "total_results" => new OA\Property(property: "total_results", type: "integer", example: 42),
                        "per_page" => new OA\Property(property: "per_page", type: "integer", example: 9)
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function reviews(Request $request, Response $response, $args)
    {
        try {
            $page = isset($args['page']) ? max(1, (int)$args['page']) : 1;
            $perPage = 9; // Fixed per page count - can be changed in code if needed
            
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';
            
            $readingModel = new \App\Models\Reading();
            $result = $readingModel->getReviewsPaginated($page, $perPage, $forceRefresh);
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
                'cached' => $result['from_cache']
            ];
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}