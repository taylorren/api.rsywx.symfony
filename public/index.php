<?php

/**
 * Production front controller.
 *
 * All wiring (middleware + routes) lives in config/ and is shared with the
 * PHPUnit bootstrap (bootstrap-app.php), so tests exercise exactly what runs
 * in production.
 */

require __DIR__ . '/../config/env.php';

$app = require __DIR__ . '/../config/app.php';

$app->run();
