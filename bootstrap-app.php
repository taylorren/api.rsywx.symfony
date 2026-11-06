<?php

/**
 * PHPUnit bootstrap: builds the same Slim app as public/index.php (shared
 * config/middleware.php + config/routes.php) but does NOT run it; it returns
 * the app instance so integration tests can handle requests directly.
 */

require __DIR__ . '/config/env.php';

// The test suite authenticates with a fixed key that phpunit.xml cannot always
// propagate into the process environment, so force it when running under
// PHPUnit (APP_ENV=testing), mirroring the old inline behaviour.
if (($_ENV['APP_ENV'] ?? '') === 'testing') {
    $_ENV['API_KEY'] = 'test-api-key-12345';
} elseif (!isset($_ENV['API_KEY'])) {
    $_ENV['API_KEY'] = getenv('API_KEY') ?: 'test-api-key-12345';
}

return require __DIR__ . '/config/app.php';
