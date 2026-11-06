<?php

/**
 * Registers the application middleware stack.
 *
 * Slim runs middleware "last added, first executed" (verified against
 * Slim\MiddlewareDispatcher, which is LIFO): the middleware added last is the
 * OUTERMOST layer. So registration below is the reverse of execution order:
 *
 *   execution (outer → inner):  CORS → API key → [timing] → Slim errors → ErrorHandler → route
 *   registration order:         ErrorHandler, Slim errors, [timing], API key, CORS
 *
 * CORS is registered last (outermost) so *every* response — including the 401
 * produced by the API-key middleware — flows back through it and gets the CORS
 * headers. ErrorHandlerMiddleware is registered first (innermost) so it renders
 * application exceptions before the framework's own error middleware sees them.
 */

return function (Slim\App $app): void {
    $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';

    $app->add(new App\Middleware\ErrorHandlerMiddleware());

    $app->addErrorMiddleware($isDev, true, true);

    if ($isDev) {
        $app->add(new App\Middleware\ResponseTimingMiddleware());
    }

    $app->add(new App\Middleware\ApiKeyMiddleware());
    $app->add(new App\Middleware\CorsMiddleware());
};
