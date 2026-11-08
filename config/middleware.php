<?php

/**
 * Registers the application middleware stack.
 *
 * Slim runs middleware "last added, first executed" (verified against
 * Slim\MiddlewareDispatcher, which is LIFO): the middleware added last is the
 * OUTERMOST layer. So registration below is the reverse of execution order:
 *
 *   execution (outer → inner):  CORS → cache → API key → [timing] → Slim errors → ErrorHandler → route
 *   registration order:         ErrorHandler, Slim errors, [timing], API key, cache, CORS
 *
 * CORS is registered last (outermost) so *every* response — including the 401
 * produced by the API-key middleware — flows back through it and gets the CORS
 * headers. ErrorHandlerMiddleware is registered first (innermost) so it renders
 * application exceptions before the framework's own error middleware sees them.
 * CacheControlMiddleware sits just inside CORS: it needs to see the final status
 * (so a 401 or a 500 is labelled `no-store`), and every response still leaves
 * through CORS.
 */

return function (Slim\App $app): void {
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';

    $app->add(new App\Middleware\ErrorHandlerMiddleware());

    $app->addErrorMiddleware($isDev, true, true);

    if ($isDev) {
        $app->add(new App\Middleware\ResponseTimingMiddleware());
    }

    $app->add(new App\Middleware\ApiKeyMiddleware());
    $app->add(new App\Middleware\CacheControlMiddleware());
    $app->add(new App\Middleware\CorsMiddleware());
};
