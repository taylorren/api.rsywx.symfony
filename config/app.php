<?php

/**
 * Builds the Slim application (middleware + routes) without running it, so the
 * exact same wiring is used by public/index.php and the PHPUnit bootstrap.
 *
 * The caller is expected to have already loaded the environment via
 * config/env.php.
 */

use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

(require __DIR__ . '/middleware.php')($app);
(require __DIR__ . '/routes.php')($app);

return $app;
