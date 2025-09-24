<?php

namespace App\Controllers;

use App\Models\BookStatus;
use App\Models\Book;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;
use Exception;

#[OA\Info(
    version: "1.0.0",
    title: "RSYWX Library API",
    description: "A comprehensive API for managing your personal library collection. Access book details, collection statistics, and more."
)]
#[OA\Server(
    url: "/api/v1",
    description: "API v1"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-API-Key",
    description: "API key required for authentication"
)]
class BookController
{
    private $bookStatusModel;

    public function __construct()
    {
        $this->bookStatusModel = new BookStatus();
    }

    #[OA\Get(
        path: "/books/status",
        summary: "藏书基本信息",
        description: "返回书籍总数、总页数、总千字数，以及总访问量",
        tags: ["Books"],
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
        description: "Collection status",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "total_books" => new OA\Property(property: "total_books", type: "integer", example: 1820),
                        "total_pages" => new OA\Property(property: "total_pages", type: "integer", example: 724621),
                        "total_kwords" => new OA\Property(property: "total_kwords", type: "integer", example: 483369),
                        "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 2514665)
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function status(Request $request, Response $response)
    {
        try {
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $result = $this->bookStatusModel->getCollectionStatus($forceRefresh);

            $data = [
                'success' => true,
                'data' => [
                    'total_books' => (int)$result['data']['total_books'],
                    'total_pages' => (int)$result['data']['total_pages'],
                    'total_kwords' => (int)$result['data']['total_kwords'],
                    'total_visits' => (int)$result['data']['total_visits']
                ],
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
        path: "/books/{bookid}",
        summary: "Get book details",
        description: "Returns detailed information for a specific book including metadata, tags, reviews, and visit statistics",
        tags: ["Book Details"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "bookid",
        in: "path",
        description: "Book ID",
        required: true,
        schema: new OA\Schema(type: "string", example: "00666")
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
        description: "Book details",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "object",
                    properties: [
                        "id" => new OA\Property(property: "id", type: "integer", example: 666),
                        "bookid" => new OA\Property(property: "bookid", type: "string", example: "00666"),
                        "title" => new OA\Property(property: "title", type: "string", example: "隐形的城市"),
                        "author" => new OA\Property(property: "author", type: "string", example: "卡尔维诺"),
                        "translated" => new OA\Property(property: "translated", type: "boolean", example: true),
                        "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: "译林出版社", nullable: true),
                        "region" => new OA\Property(property: "region", type: "string", example: "意大利"),
                        "location" => new OA\Property(property: "location", type: "string", example: "书房"),
                        "purchdate" => new OA\Property(property: "purchdate", type: "string", example: "2020-05-15"),
                        "price" => new OA\Property(property: "price", type: "number", example: 45.00),
                        "pubdate" => new OA\Property(property: "pubdate", type: "string", example: "2019-03-01"),
                        "printdate" => new OA\Property(property: "printdate", type: "string", example: "2019-03-15"),
                        "ver" => new OA\Property(property: "ver", type: "string", example: "1"),
                        "deco" => new OA\Property(property: "deco", type: "string", example: "精装"),
                        "isbn" => new OA\Property(property: "isbn", type: "string", example: "978-7-5447-6789-0"),
                        "category" => new OA\Property(property: "category", type: "string", example: "文学", nullable: true),
                        "ol" => new OA\Property(property: "ol", type: "string", example: "cn", nullable: true),
                        "kword" => new OA\Property(property: "kword", type: "integer", example: 120),
                        "page" => new OA\Property(property: "page", type: "integer", example: 280),
                        "intro" => new OA\Property(property: "intro", type: "string", example: "Book introduction text"),
                        "instock" => new OA\Property(property: "instock", type: "boolean", example: true),
                        "publisher_name" => new OA\Property(property: "publisher_name", type: "string", example: "花城出版社"),
                        "place_name" => new OA\Property(property: "place_name", type: "string", example: "上海"),
                        "tags" => new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string"), example: ["意大利", "散文", "文学", "经典"]),
                        "reviews" => new OA\Property(property: "reviews", type: "array", items: new OA\Items(type: "object")),
                        "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/00666.jpg"),
                        "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 4843),
                        "last_visited" => new OA\Property(property: "last_visited", type: "string", example: "2025-07-27 07:29:57")
                    ]
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Book not found",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: false),
                "message" => new OA\Property(property: "message", type: "string", example: "Book not found")
            ]
        )
    )]
    public function show(Request $request, Response $response, $args)
    {
        try {
            $bookid = $args['bookid'];
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new Book();
            $result = $bookModel->getBookDetail($bookid, $forceRefresh);

            if ($result === null) {
                $errorData = [
                    'success' => false,
                    'message' => 'Book not found'
                ];

                $response->getBody()->write(json_encode($errorData));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

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
        path: "/books/latest/{count}",
        summary: "Get latest purchased books",
        description: "Returns the most recently purchased books, ordered by purchase date (newest first)",
        tags: ["Book Lists"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of books to return (defaults to 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 100, example: 5)
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
        description: "Latest purchased books",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 2083),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "02083"),
                            "title" => new OA\Property(property: "title", type: "string", example: "维吉尔之死"),
                            "author" => new OA\Property(property: "author", type: "string", example: "布洛赫"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: true),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: "译林出版社", nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "奥地利"),
                            "location" => new OA\Property(property: "location", type: "string", example: "书房"),
                            "publisher_name" => new OA\Property(property: "publisher_name", type: "string", example: "译林出版社"),
                            "place_name" => new OA\Property(property: "place_name", type: "string", example: "苏州"),
                            "purchdate" => new OA\Property(property: "purchdate", type: "string", example: "2025-07-07"),
                            "price" => new OA\Property(property: "price", type: "number", example: 88.00),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/02083.jpg")
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
            $count = max(1, min(100, $count)); // Ensure count is between 1 and 100

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getLatestBooks($count, $forceRefresh);

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
        path: "/books/random/{count}",
        summary: "Get random books",
        description: "Returns a random selection of books from your library collection",
        tags: ["Book Lists"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of random books to return (defaults to 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50, example: 5)
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
        description: "Random books from collection",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 1234),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "title" => new OA\Property(property: "title", type: "string", example: "随机书籍标题"),
                            "author" => new OA\Property(property: "author", type: "string", example: "作者姓名"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: false),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: null, nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "中国"),
                            "location" => new OA\Property(property: "location", type: "string", example: "书房"),
                            "publisher_name" => new OA\Property(property: "publisher_name", type: "string", example: "出版社名称"),
                            "place_name" => new OA\Property(property: "place_name", type: "string", example: "出版地"),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg"),
                            "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 123),
                            "last_visited" => new OA\Property(property: "last_visited", type: "string", example: "2025-07-27 10:30:00")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: false)
            ]
        )
    )]
    public function random(Request $request, Response $response, $args)
    {
        try {
            $count = isset($args['count']) ? (int)$args['count'] : 1;
            $count = max(1, min(50, $count)); // Ensure count is between 1 and 50

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getRandomBooks($count, $forceRefresh);

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
        path: "/books/last_visited/{count}",
        summary: "Get recently visited books",
        description: "Returns books ordered by most recent visit time (most recently visited first)",
        tags: ["Book Lists"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of recently visited books to return (defaults to 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50, example: 5)
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
        description: "Recently visited books",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 1234),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "title" => new OA\Property(property: "title", type: "string", example: "最近访问的书籍"),
                            "author" => new OA\Property(property: "author", type: "string", example: "作者姓名"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: false),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: null, nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "中国", description: "Author's region"),
                            "location" => new OA\Property(property: "location", type: "string", example: "书房"),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg"),
                            "last_visited" => new OA\Property(property: "last_visited", type: "string", example: "2025-07-27 12:30:00"),
                            "visit_country" => new OA\Property(property: "visit_country", type: "string", example: "China", description: "Country where the book was accessed", nullable: true),
                            "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 15, description: "Total number of times this book has been visited")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function lastVisited(Request $request, Response $response, $args)
    {
        try {
            $count = isset($args['count']) ? (int)$args['count'] : 1;
            $count = max(1, min(50, $count)); // Ensure count is between 1 and 50

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getLastVisitedBooks($count, $forceRefresh);

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
        path: "/books/forgotten/{count}",
        summary: "Get forgotten books",
        description: "Returns books that haven't been visited for a long time, ordered by oldest visit first (most forgotten first)",
        tags: ["Book Lists"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of forgotten books to return (defaults to 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50, example: 5)
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
        description: "Forgotten books (not visited recently)",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 1234),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "title" => new OA\Property(property: "title", type: "string", example: "被遗忘的书籍"),
                            "author" => new OA\Property(property: "author", type: "string", example: "作者姓名"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: false),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: null, nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "中国"),
                            "location" => new OA\Property(property: "location", type: "string", example: "书房"),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg"),
                            "last_visited" => new OA\Property(property: "last_visited", type: "string", example: "2024-01-15 10:30:00"),
                            "days_since_visit" => new OA\Property(property: "days_since_visit", type: "integer", example: 180, description: "Number of days since last visit")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true)
            ]
        )
    )]
    public function forgotten(Request $request, Response $response, $args)
    {
        try {
            $count = isset($args['count']) ? (int)$args['count'] : 1;
            $count = max(1, min(50, $count)); // Ensure count is between 1 and 50

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getForgottenBooks($count, $forceRefresh);

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
        path: "/books/today/{month}/{date}",
        summary: "Get books for specific date",
        description: "Returns books purchased on a specific date in previous years (excluding current year) - like 'on this day in history' for your book collection.",
        tags: ["Book Lists"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "month",
        in: "path",
        description: "Month (1-12)",
        required: true,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 12, example: 8)
    )]
    #[OA\Parameter(
        name: "date",
        in: "path",
        description: "Day of month (1-31)",
        required: true,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 31, example: 7)
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
        description: "Books purchased on the specified date in previous years",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 1234),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "title" => new OA\Property(property: "title", type: "string", example: "历史书籍"),
                            "author" => new OA\Property(property: "author", type: "string", example: "作者姓名"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: false),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: null, nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "中国"),
                            "location" => new OA\Property(property: "location", type: "string", example: "f3", description: "Physical location/shelf code where the book is stored"),
                            "publisher_name" => new OA\Property(property: "publisher_name", type: "string", example: "出版社名称"),
                            "place_name" => new OA\Property(property: "place_name", type: "string", example: "购买地点"),
                            "purchdate" => new OA\Property(property: "purchdate", type: "string", example: "2020-08-07"),
                            "price" => new OA\Property(property: "price", type: "number", example: 25.50),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg"),
                            "years_ago" => new OA\Property(property: "years_ago", type: "integer", example: 5, description: "How many years ago this book was purchased")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true),
                "date_info" => new OA\Property(
                    property: "date_info",
                    type: "object",
                    properties: [
                        "requested_date" => new OA\Property(property: "requested_date", type: "string", example: "2025-08-07"),
                        "month_day" => new OA\Property(property: "month_day", type: "string", example: "08-07"),
                        "is_today" => new OA\Property(property: "is_today", type: "boolean", example: true)
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid date parameters",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: false),
                "message" => new OA\Property(property: "message", type: "string", example: "Invalid date: month must be 1-12, date must be 1-31")
            ]
        )
    )]
    public function todayWithParams(Request $request, Response $response, $args)
    {
        try {
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            // Get month and date from route parameters
            $month = (int)$args['month'];
            $date = (int)$args['date'];

            // Validate parameters
            if ($month < 1 || $month > 12 || $date < 1 || $date > 31) {
                $errorData = [
                    'success' => false,
                    'message' => 'Invalid date: month must be 1-12, date must be 1-31'
                ];
                $response->getBody()->write(json_encode($errorData));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Check if the date is valid using a leap year (2020) to allow Feb 29
            // Since we're looking for books from previous years, Feb 29 is valid in leap years
            if (!checkdate($month, $date, 2020)) {
                $errorData = [
                    'success' => false,
                    'message' => 'Invalid date: the specified month and date combination does not exist'
                ];
                $response->getBody()->write(json_encode($errorData));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getTodaysBooks($month, $date, $forceRefresh);

            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache'],
                'date_info' => $result['date_info']
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
        path: "/books/today",
        summary: "Get today's books",
        description: "Returns books purchased on today's date in previous years (excluding current year) - like 'on this day in history' for your book collection.",
        tags: ["Book Lists"],
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
        description: "Books purchased on today's date in previous years",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 1234),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                            "title" => new OA\Property(property: "title", type: "string", example: "今日历史书籍"),
                            "author" => new OA\Property(property: "author", type: "string", example: "作者姓名"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: false),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: null, nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "中国"),
                            "location" => new OA\Property(property: "location", type: "string", example: "f3", description: "Physical location/shelf code where the book is stored"),
                            "publisher_name" => new OA\Property(property: "publisher_name", type: "string", example: "出版社名称"),
                            "place_name" => new OA\Property(property: "place_name", type: "string", example: "购买地点"),
                            "purchdate" => new OA\Property(property: "purchdate", type: "string", example: "2020-08-07"),
                            "price" => new OA\Property(property: "price", type: "number", example: 25.50),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg"),
                            "years_ago" => new OA\Property(property: "years_ago", type: "integer", example: 5, description: "How many years ago this book was purchased")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true),
                "date_info" => new OA\Property(
                    property: "date_info",
                    type: "object",
                    properties: [
                        "requested_date" => new OA\Property(property: "requested_date", type: "string", example: "2025-08-07"),
                        "month_day" => new OA\Property(property: "month_day", type: "string", example: "08-07"),
                        "is_today" => new OA\Property(property: "is_today", type: "boolean", example: true)
                    ]
                )
            ]
        )
    )]
    public function today(Request $request, Response $response)
    {
        try {
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            // Use today's date
            $month = (int)date('n');
            $date = (int)date('j');

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getTodaysBooks($month, $date, $forceRefresh);

            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache'],
                'date_info' => $result['date_info']
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
        path: "/books/visit_history",
        summary: "Get visit count history",
        description: "Returns daily visit counts for the past 30 days, useful for creating visit trend graphs",
        tags: ["Collection Statistics"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "days",
        in: "query",
        description: "Number of days to include (defaults to 30, max 365)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 365, example: 30)
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
        description: "Daily visit counts for the specified period",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "date" => new OA\Property(property: "date", type: "string", example: "2025-08-07"),
                            "visit_count" => new OA\Property(property: "visit_count", type: "integer", example: 45),
                            "day_of_week" => new OA\Property(property: "day_of_week", type: "string", example: "Thursday")
                        ]
                    )
                ),
                "cached" => new OA\Property(property: "cached", type: "boolean", example: true),
                "period_info" => new OA\Property(
                    property: "period_info",
                    type: "object",
                    properties: [
                        "start_date" => new OA\Property(property: "start_date", type: "string", example: "2025-07-08"),
                        "end_date" => new OA\Property(property: "end_date", type: "string", example: "2025-08-07"),
                        "total_days" => new OA\Property(property: "total_days", type: "integer", example: 30),
                        "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 1250)
                    ]
                )
            ]
        )
    )]
    public function visitHistory(Request $request, Response $response)
    {
        try {
            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            // Get days parameter, default to 30, max 365
            $days = isset($queryParams['days']) ? (int)$queryParams['days'] : 30;
            $days = max(1, min(365, $days)); // Ensure days is between 1 and 365

            $bookModel = new \App\Models\Book();
            $result = $bookModel->getVisitHistory($days, $forceRefresh);

            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['from_cache'],
                'period_info' => $result['period_info']
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
        path: "/books/list/{type?}/{value?}/{page?}",
        summary: "List/search books",
        description: "Search books by author, title, tag, or misc criteria with pagination. All parameters are optional. Default: list all books ordered by id DESC, page 1",
        tags: ["Book Lists"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "type",
        in: "path",
        description: "Search type: author, title, tag, misc, id (default: id)",
        required: false,
        schema: new OA\Schema(type: "string", enum: ["author", "title", "tag", "misc", "id"], example: "author", default: "id")
    )]
    #[OA\Parameter(
        name: "value",
        in: "path",
        description: "Search value (optional for type=id)",
        required: false,
        schema: new OA\Schema(type: "string", example: "卡尔维诺")
    )]
    #[OA\Parameter(
        name: "page",
        in: "path",
        description: "Page number (1-based, default: 1)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, example: 1, default: 1)
    )]
    #[OA\Response(
        response: 200,
        description: "List of books matching search criteria",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "id" => new OA\Property(property: "id", type: "integer", example: 666),
                            "bookid" => new OA\Property(property: "bookid", type: "string", example: "00666"),
                            "title" => new OA\Property(property: "title", type: "string", example: "隐形的城市"),
                            "author" => new OA\Property(property: "author", type: "string", example: "卡尔维诺"),
                            "translated" => new OA\Property(property: "translated", type: "boolean", example: true),
                            "copyrighter" => new OA\Property(property: "copyrighter", type: "string", example: "译林出版社", nullable: true),
                            "region" => new OA\Property(property: "region", type: "string", example: "意大利"),
                            "location" => new OA\Property(property: "location", type: "string", example: "书房"),
                            "purchdate" => new OA\Property(property: "purchdate", type: "string", example: "2020-05-15"),
                            "tags" => new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string"), example: ["意大利", "文学", "经典"]),
                            "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/00666.jpg")
                        ]
                    )
                ),
                "pagination" => new OA\Property(
                    property: "pagination",
                    type: "object",
                    properties: [
                        "current_page" => new OA\Property(property: "current_page", type: "integer", example: 1),
                        "total_pages" => new OA\Property(property: "total_pages", type: "integer", example: 5),
                        "total_results" => new OA\Property(property: "total_results", type: "integer", example: 23),
                        "per_page" => new OA\Property(property: "per_page", type: "integer", example: 20)
                    ]
                )
            ]
        )
    )]
    public function listBooks(Request $request, Response $response, $args)
    {
        try {
            // Handle the case where a single numeric parameter is provided (as page number)
            if (isset($args['type']) && is_numeric($args['type']) && !isset($args['value'])) {
                $type = 'title';
                $value = '-';
                $page = (int)$args['type'];
            } else {
                // Set default type to 'title' and validate allowed types
                $type = $args['type'] ?? 'title';
                if (!in_array($type, ['title', 'author', 'tags', 'misc'])) {
                    throw new \InvalidArgumentException("Invalid type. Allowed types are: title, author, tags, misc");
                }

                // Handle value parameter with special case for '-'
                $value = isset($args['value']) ? urldecode($args['value']) : '-';  // default to '-' for wildcard
                
                $page = isset($args['page']) ? (int)$args['page'] : 1;
            }
            
            $bookModel = new Book();
            $result = $bookModel->listBooks($type, $value, $page);
            
            $data = [
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
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

    #[OA\Post(
        path: "/books/{sbookid}/tags",
        summary: "Add tags to a book",
        description: "Add one or more tags to a book. Duplicate tags are ignored.",
        tags: ["Book Management"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "bookid",
        in: "path",
        description: "Book ID (5 digits)",
        required: true,
        schema: new OA\Schema(type: "string", pattern: "^[0-9]{5}$", example: "00666")
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                "tags" => new OA\Property(
                    property: "tags",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["经典", "文学", "推荐"]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Tags added successfully",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "message" => new OA\Property(property: "message", type: "string", example: "Tags added successfully"),
                "added_tags" => new OA\Property(
                    property: "added_tags",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["经典", "文学"]
                ),
                "duplicate_tags" => new OA\Property(
                    property: "duplicate_tags",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["推荐"]
                )
            ]
        )
    )]
    public function addTags(Request $request, Response $response, $args)
    {
        try {
            $bookid = $args['bookid'];
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            
            if (!isset($data['tags']) || !is_array($data['tags'])) {
                throw new \InvalidArgumentException('Tags array is required');
            }
            
            $bookModel = new Book();
            $result = $bookModel->addBookTags($bookid, $data['tags']);
            
            $responseData = [
                'success' => true,
                'message' => 'Tags processed successfully',
                'added_tags' => $result['added'],
                'duplicate_tags' => $result['duplicates']
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: "/books/{bookid}/related/{count}",
        summary: "Get related books",
        description: "Returns books related to the specified book based on tags, author, category, and behavioral patterns",
        tags: ["Book Recommendations"],
        security: [["ApiKeyAuth" => []]]
    )]
    #[OA\Parameter(
        name: "bookid",
        in: "path",
        description: "Book ID (5 digits)",
        required: true,
        schema: new OA\Schema(type: "string", pattern: "^[0-9]{5}$", example: "00666")
    )]
    #[OA\Parameter(
        name: "count",
        in: "path",
        description: "Number of related books to return (defaults to 5)",
        required: false,
        schema: new OA\Schema(type: "integer", minimum: 1, maximum: 20, example: 5)
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
        description: "Related books with similarity scores",
        content: new OA\JsonContent(
            properties: [
                "success" => new OA\Property(property: "success", type: "boolean", example: true),
                "data" => new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            "book" => new OA\Property(
                                property: "book",
                                type: "object",
                                properties: [
                                    "id" => new OA\Property(property: "id", type: "integer", example: 1234),
                                    "bookid" => new OA\Property(property: "bookid", type: "string", example: "01234"),
                                    "title" => new OA\Property(property: "title", type: "string", example: "相关书籍标题"),
                                    "author" => new OA\Property(property: "author", type: "string", example: "作者姓名"),
                                    "tags" => new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string")),
                                    "cover_uri" => new OA\Property(property: "cover_uri", type: "string", example: "https://api.rsywx.com/covers/01234.jpg")
                                ]
                            ),
                            "similarity_score" => new OA\Property(property: "similarity_score", type: "number", example: 0.87),
                            "relationship_reasons" => new OA\Property(
                                property: "relationship_reasons",
                                type: "array",
                                items: new OA\Items(type: "string"),
                                example: ["共同标签: 文学, 经典", "同一作者"]
                            )
                        ]
                    )
                ),
                "algorithm_info" => new OA\Property(
                    property: "algorithm_info",
                    type: "object",
                    properties: [
                        "primary_factors" => new OA\Property(property: "primary_factors", type: "array", items: new OA\Items(type: "string")),
                        "computation_time" => new OA\Property(property: "computation_time", type: "string", example: "12ms"),
                        "cached" => new OA\Property(property: "cached", type: "boolean", example: false)
                    ]
                )
            ]
        )
    )]
    public function getRelatedBooks(Request $request, Response $response, $args)
    {
        try {
            $startTime = microtime(true);
            $bookid = $args['bookid'];
            $count = isset($args['count']) ? (int)$args['count'] : 5;
            $count = max(1, min(20, $count)); // Ensure count is between 1 and 20

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new Book();
            $result = $bookModel->getRelatedBooks($bookid, $count, $forceRefresh);

            if ($result === null) {
                $errorData = [
                    'success' => false,
                    'message' => 'Book not found'
                ];

                $response->getBody()->write(json_encode($errorData));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $computationTime = round((microtime(true) - $startTime) * 1000, 2);

            $data = [
                'success' => true,
                'data' => $result['data'],
                'categories' => $result['categories'] ?? [],
                'discovery_info' => $result['discovery_info'] ?? [],
                'algorithm_info' => [
                    'primary_factors' => $result['primary_factors'],
                    'computation_time' => $computationTime . 'ms',
                    'cached' => $result['from_cache']
                ]
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
        path: "/books/popular/{count}",
        summary: "Get most popular books",
        description: "Returns the most popular books ordered by total visit count in descending order",
        tags: ["Books"],
        parameters: [
            new OA\Parameter(
                name: "count",
                in: "path",
                required: true,
                description: "Number of popular books to return (1-50)",
                schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50)
            ),
            new OA\Parameter(
                name: "refresh",
                in: "query",
                required: false,
                description: "Force refresh cache",
                schema: new OA\Schema(type: "string", enum: ["true", "false"])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Most popular books retrieved successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        "success" => new OA\Property(property: "success", type: "boolean", example: true),
                        "data" => new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    "id" => new OA\Property(property: "id", type: "integer", example: 123),
                                    "bookid" => new OA\Property(property: "bookid", type: "string", example: "BK001"),
                                    "title" => new OA\Property(property: "title", type: "string", example: "红楼梦"),
                                    "author" => new OA\Property(property: "author", type: "string", example: "曹雪芹"),
                                    "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 1250),
                                    "last_visited" => new OA\Property(property: "last_visited", type: "string", format: "date-time", example: "2024-01-15 14:30:00"),
                                    "purchdate" => new OA\Property(property: "purchdate", type: "string", format: "date", example: "2023-05-20"),
                                    "place_name" => new OA\Property(property: "place_name", type: "string", example: "北京书店")
                                ]
                            )
                        ),
                        "cached" => new OA\Property(property: "cached", type: "boolean", example: false)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Invalid count parameter",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        "success" => new OA\Property(property: "success", type: "boolean", example: false),
                        "message" => new OA\Property(property: "message", type: "string", example: "Count must be between 1 and 50")
                    ]
                )
            )
        ]
    )]
    public function popular(Request $request, Response $response, $args)
    {
        try {
            $count = isset($args['count']) ? (int)$args['count'] : 10;
            
            // Validate count parameter
            if ($count < 1 || $count > 50) {
                $errorData = [
                    'success' => false,
                    'message' => 'Count must be between 1 and 50'
                ];
                
                $response->getBody()->write(json_encode($errorData));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new Book();
            $result = $bookModel->getMostPopularBooks($count, $forceRefresh);

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
        path: "/books/unpopular/{count}",
        summary: "Get most unpopular books",
        description: "Returns the most unpopular books ordered by total visit count in ascending order",
        tags: ["Books"],
        parameters: [
            new OA\Parameter(
                name: "count",
                in: "path",
                required: true,
                description: "Number of unpopular books to return (1-50)",
                schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50)
            ),
            new OA\Parameter(
                name: "refresh",
                in: "query",
                required: false,
                description: "Force refresh cache",
                schema: new OA\Schema(type: "string", enum: ["true", "false"])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Most unpopular books retrieved successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        "success" => new OA\Property(property: "success", type: "boolean", example: true),
                        "data" => new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    "id" => new OA\Property(property: "id", type: "integer", example: 456),
                                    "bookid" => new OA\Property(property: "bookid", type: "string", example: "BK002"),
                                    "title" => new OA\Property(property: "title", type: "string", example: "冷门书籍"),
                                    "author" => new OA\Property(property: "author", type: "string", example: "某作者"),
                                    "total_visits" => new OA\Property(property: "total_visits", type: "integer", example: 5),
                                    "last_visited" => new OA\Property(property: "last_visited", type: "string", format: "date-time", example: "2023-01-15 14:30:00"),
                                    "purchdate" => new OA\Property(property: "purchdate", type: "string", format: "date", example: "2022-05-20"),
                                    "place_name" => new OA\Property(property: "place_name", type: "string", example: "网上书店")
                                ]
                            )
                        ),
                        "cached" => new OA\Property(property: "cached", type: "boolean", example: false)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Invalid count parameter",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        "success" => new OA\Property(property: "success", type: "boolean", example: false),
                        "message" => new OA\Property(property: "message", type: "string", example: "Count must be between 1 and 50")
                    ]
                )
            )
        ]
    )]
    public function unpopular(Request $request, Response $response, $args)
    {
        try {
            $count = isset($args['count']) ? (int)$args['count'] : 10;
            
            // Validate count parameter
            if ($count < 1 || $count > 50) {
                $errorData = [
                    'success' => false,
                    'message' => 'Count must be between 1 and 50'
                ];
                
                $response->getBody()->write(json_encode($errorData));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $queryParams = $request->getQueryParams();
            $forceRefresh = isset($queryParams['refresh']) && $queryParams['refresh'] === 'true';

            $bookModel = new Book();
            $result = $bookModel->getMostUnpopularBooks($count, $forceRefresh);

            $data = [
                'success' => true,
                'data' => $result['data'],
                'cached' => $result['cached']
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $errorData = [
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($errorData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
