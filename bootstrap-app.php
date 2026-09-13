<?php

/**
 * Test bootstrap: builds the same Slim app as public/index.php (routes +
 * middleware) but does not run it. Returns the app instance for integration
 * tests. Requires Dotenv / DB env to be available (see .env).
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/vendor/autoload.php';

// Load environment variables (same as public/index.php)
// phpunit.xml references ${VAR} placeholders which, when the underlying var is
// absent from the process environment, end up as literal "${VAR}" strings in
// $_ENV — blocking Dotenv from filling real values from .env. Clear any such
// unresolved placeholders first.
foreach (array_keys($_ENV) as $key) {
    if (is_string($_ENV[$key]) && preg_match('/^\$\{.+\}$/', $_ENV[$key])) {
        $_ENV[$key] = null;
        unset($_ENV[$key]);
        putenv($key);
    }
}
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Test overrides for the API key middleware. When running under phpunit
// (APP_ENV=testing from phpunit.xml), force the key the tests authenticate
// with — phpunit.xml's ${API_KEY} indirection can resolve to a literal
// placeholder when the var is absent from the process environment.
if (($_ENV['APP_ENV'] ?? '') === 'testing') {
    $_ENV['API_KEY'] = 'test-api-key-12345';
} elseif (!isset($_ENV['API_KEY'])) {
    $_ENV['API_KEY'] = getenv('API_KEY') ?: 'test-api-key-12345';
}

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(
    ($_ENV['APP_ENV'] ?? 'production') === 'development',
    true,
    true
);

// Add CORS middleware
$app->add(function (Request $request, $handler) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withStatus(200);
    }

    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Max-Age', '86400');
});

// API Key middleware
$app->add(function (Request $request, $handler) {
    $uri = $request->getUri()->getPath();

    $healthAlias = '/api/' . ($_ENV['API_VERSION'] ?? 'v1') . '/health';
    if ($uri === '/' || $uri === '/health' || $uri === $healthAlias || strpos($uri, '/api-docs') === 0 || $request->getMethod() === 'OPTIONS') {
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
            ->withHeader('Content-Type', 'application/json');
    }

    return $handler->handle($request);
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

// API routes (identical to public/index.php)
$app->group('/api/' . $_ENV['API_VERSION'], function ($group) {
    // Health check alias under the versioned API (also available at /health)
    $group->get('/health', \App\Controllers\SystemController::class . ':health');

    $group->get('/books/status', \App\Controllers\BookController::class . ':status');
    $group->get('/books/latest[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':latest');
    $group->get('/books/random[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':random');
    $group->get('/books/last_visited[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':lastVisited');
    $group->get('/books/forgotten[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':forgotten');
    $group->get('/books/popular/{count:[0-9]+}', \App\Controllers\BookController::class . ':popular');
    $group->get('/books/unpopular/{count:[0-9]+}', \App\Controllers\BookController::class . ':unpopular');
    $group->get('/books/today/{month:[0-9]+}/{date:[0-9]+}', \App\Controllers\BookController::class . ':todayWithParams');
    $group->get('/books/today', \App\Controllers\BookController::class . ':today');
    $group->get('/books/visit_history', \App\Controllers\BookController::class . ':visitHistory');
    $group->get('/books/list[/{type}[/{value}[/{page:[0-9]+}]]]', \App\Controllers\BookController::class . ':listBooks');
    $group->get('/books/{bookid:[0-9]{5}}', \App\Controllers\BookController::class . ':show');

    $group->get('/misc/wotd', \App\Controllers\MiscController::class . ':wordOfTheDay');
    $group->get('/misc/qotd', \App\Controllers\MiscController::class . ':qotd');
    $group->get('/misc/weather/current', \App\Controllers\MiscController::class . ':currentWeather');
    $group->get('/misc/weather/forecast', \App\Controllers\MiscController::class . ':weatherForecast');

    $group->post('/books/{bookid:[0-9]{5}}/tags', \App\Controllers\BookController::class . ':addTags');
    $group->get('/books/{bookid:[0-9]{5}}/related[/{count:[0-9]+}]', \App\Controllers\BookController::class . ':getRelatedBooks');

    $group->get('/readings/summary', \App\Controllers\ReadingController::class . ':summary');
    $group->get('/readings/latest[/{count:[0-9]+}]', \App\Controllers\ReadingController::class . ':latest');
    $group->get('/readings/reviews[/{page:[0-9]+}]', \App\Controllers\ReadingController::class . ':reviews');

    $group->get('/wp/posts/today/{month:[0-9]+}/{day:[0-9]+}', \App\Controllers\WordPressController::class . ':getPostsOnThisDay');
    $group->get('/wp/posts/today', \App\Controllers\WordPressController::class . ':getTodaysPosts');
});

return $app;
