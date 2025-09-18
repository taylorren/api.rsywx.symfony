<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_ENV'] === 'development',
    true,
    true
);

// Add performance timing middleware (development only)
if ($_ENV['APP_ENV'] === 'development') {
    $app->add(function (Request $request, $handler) {
        $start = microtime(true);
        $response = $handler->handle($request);
        $duration = round((microtime(true) - $start) * 1000, 2);

        return $response->withHeader('X-Response-Time', $duration . 'ms');
    });
}

// Add CORS middleware
$app->add(function (Request $request, $handler) {
    // Handle preflight OPTIONS request
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('X-Debug-Origin', $request->getHeaderLine('Origin') ?: 'no-origin')
            ->withHeader('X-Debug-Host', $request->getHeaderLine('Host') ?: 'no-host')
            ->withStatus(200);
    }

    $response = $handler->handle($request);

    // Preserve existing headers (like X-Response-Time) and add CORS headers
    $existingHeaders = $response->getHeaders();
    $corsResponse = $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Max-Age', '86400');

    // Ensure X-Response-Time is preserved if it exists
    if (isset($existingHeaders['X-Response-Time'])) {
        $corsResponse = $corsResponse->withHeader('X-Response-Time', $existingHeaders['X-Response-Time'][0]);
    }

    return $corsResponse;
});

// API Key middleware
$app->add(function (Request $request, $handler) {
    $uri = $request->getUri()->getPath();

    // Skip auth for documentation, health check, static files, and OPTIONS requests
    if ($uri === '/' || $uri === '/health' || strpos($uri, '/api-docs') === 0 || $request->getMethod() === 'OPTIONS') {
        return $handler->handle($request);
    }

    $apiKey = $request->getHeaderLine('X-API-Key') ?: $request->getQueryParams()['api_key'] ?? null;

    if (!$apiKey || $apiKey !== $_ENV['API_KEY']) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Invalid or missing API key'
        ]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }

    return $handler->handle($request);
});

// API Documentation (no auth required)
$app->get('/', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/api-docs.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Health check endpoint (no auth required)
$app->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// API routes
$app->group('/api/' . $_ENV['API_VERSION'], function ($group) {
    // Book endpoints
    $group->get('/books/status', \App\Controllers\BookController::class . ':status');
    $group->get('/books/latest[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':latest');
    $group->get('/books/random[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':random');
    $group->get('/books/last_visited[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':lastVisited');
    $group->get('/books/forgotten[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':forgotten');
    $group->get('/books/popular/{count:[0-9]+}', \App\Controllers\BookController::class . ':popular');
    $group->get('/books/today/{month:[0-9]+}/{date:[0-9]+}', \App\Controllers\BookController::class . ':todayWithParams');
    $group->get('/books/today', \App\Controllers\BookController::class . ':today');
    $group->get('/books/visit_history', \App\Controllers\BookController::class . ':visitHistory');
    $group->get('/books/list[/{type}[/{value}[/{page:[0-9]+}]]]', \App\Controllers\BookController::class . ':listBooks');
    $group->get('/books/{bookid:[0-9]{5}}', \App\Controllers\BookController::class . ':show');

    // Miscellaneous endpoints
    $group->get('/misc/wotd', \App\Controllers\MiscController::class . ':wordOfTheDay');
    $group->get('/misc/qotd', \App\Controllers\MiscController::class . ':qotd');
    $group->get('/misc/weather/current', \App\Controllers\MiscController::class . ':currentWeather');
    $group->get('/misc/weather/forecast', \App\Controllers\MiscController::class . ':weatherForecast');
    
    // Tag management endpoint
    $group->post('/books/{bookid:[0-9]{5}}/tags', \App\Controllers\BookController::class . ':addTags');
    
    // Related books endpoint
    $group->get('/books/{bookid:[0-9]{5}}/related[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':getRelatedBooks');

    // Reading statistics endpoints
    $group->get('/readings/summary', \App\Controllers\ReadingController::class . ':summary');
    $group->get('/readings/latest[/{count:[0-9]+}]', \App\Controllers\ReadingController::class . ':latest');
    $group->get('/readings/reviews[/{page:[0-9]+}]', \App\Controllers\ReadingController::class . ':reviews');

    // WordPress endpoint - posts on this day in history
    $group->get('/wp/posts/today/{month:[0-9]+}/{day:[0-9]+}', \App\Controllers\WordPressController::class . ':getPostsOnThisDay');
    $group->get('/wp/posts/today', \App\Controllers\WordPressController::class . ':getTodaysPosts');
});

$app->run();
