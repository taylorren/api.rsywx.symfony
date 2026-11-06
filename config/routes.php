<?php

/**
 * Single source of truth for all routes. Shared by public/index.php and the
 * PHPUnit bootstrap so the two can never drift apart.
 */

use App\Controllers\BlogController;
use App\Controllers\BookController;
use App\Controllers\MiscController;
use App\Controllers\ReadingController;
use App\Controllers\SystemController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (Slim\App $app): void {
    // Interactive API documentation (no auth) and the generated spec.
    $app->get('/', SystemController::class . ':docs');
    $app->get('/api-docs.json', SystemController::class . ':openApiDocs');
    $app->get('/api-docs.yaml', SystemController::class . ':openApiDocsYaml');

    // Health check (no auth) — one handler for the bare and versioned paths.
    $app->get('/health', SystemController::class . ':health');

    $app->group('/api/' . ($_ENV['API_VERSION'] ?? 'v1'), function ($group) {
        // Health check alias under the versioned API (also available at /health)
        $group->get('/health', SystemController::class . ':health');

        // Book endpoints
        $group->get('/books/status', BookController::class . ':status');
        $group->get('/books/latest[/{count:[0-9]+}]', BookController::class . ':latest');
        $group->get('/books/random[/{count:[0-9]+}]', BookController::class . ':random');
        $group->get('/books/last_visited[/{count:[0-9]+}]', BookController::class . ':lastVisited');
        $group->get('/books/forgotten[/{count:[0-9]+}]', BookController::class . ':forgotten');
        $group->get('/books/popular/{count:[0-9]+}', BookController::class . ':popular');
        $group->get('/books/unpopular/{count:[0-9]+}', BookController::class . ':unpopular');
        $group->get('/books/today/{month:[0-9]+}/{date:[0-9]+}', BookController::class . ':todayWithParams');
        $group->get('/books/today', BookController::class . ':today');
        $group->get('/books/visit_history', BookController::class . ':visitHistory');
        $group->get('/books/list[/{type}[/{value:.*}]]', BookController::class . ':listBooks');
        $group->get('/books/{bookid:[0-9]{5}}', BookController::class . ':show');

        // Miscellaneous endpoints
        $group->get('/misc/wotd', MiscController::class . ':wordOfTheDay');
        $group->get('/misc/qotd', MiscController::class . ':qotd');
        $group->get('/misc/weather/current', MiscController::class . ':currentWeather');
        $group->get('/misc/weather/forecast', MiscController::class . ':weatherForecast');

        // Tag management endpoint
        $group->post('/books/{bookid:[0-9]{5}}/tags', BookController::class . ':addTags');

        // Related books endpoint
        $group->get('/books/{bookid:[0-9]{5}}/related[/{count:[0-9]+}]', BookController::class . ':getRelatedBooks');

        // Reading statistics endpoints
        $group->get('/readings/summary', ReadingController::class . ':summary');
        $group->get('/readings/latest[/{count:[0-9]+}]', ReadingController::class . ':latest');
        $group->get('/readings/reviews[/{page:[0-9]+}]', ReadingController::class . ':reviews');

        // Blog endpoints
        $group->get('/blogs/today/{month:[0-9]+}/{day:[0-9]+}', BlogController::class . ':todayWithParams');
        $group->get('/blogs/today', BlogController::class . ':today');
        $group->get('/blogs/latest[/{count:[0-9]+}]', BlogController::class . ':latest');

        // Admin (authenticated) — cache maintenance
        $group->post('/admin/cache/clear', SystemController::class . ':clearCache');
    });
};
