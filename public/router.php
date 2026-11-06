<?php

/**
 * Router for the PHP built-in dev server so it behaves like Apache + .htaccess:
 * serve existing files directly, otherwise hand everything (including paths
 * with a file extension such as /api-docs.json) to the front controller.
 *
 * Usage: php -S localhost:8000 -t public public/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the static file
}

require __DIR__ . '/index.php';
