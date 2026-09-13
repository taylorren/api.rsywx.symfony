<?php

/**
 * PHPUnit bootstrap: PHPUnit 9 does not interpolate ${VAR} placeholders in
 * phpunit.xml, so any unresolved one ends up as a literal "${VAR}" string in
 * $_ENV. Clear those, then load the real .env so models and middleware see
 * proper configuration.
 */

require __DIR__ . '/../vendor/autoload.php';

foreach (array_keys($_ENV) as $key) {
    if (is_string($_ENV[$key]) && preg_match('/^\$\{.+\}$/', $_ENV[$key])) {
        unset($_ENV[$key]);
        putenv($key);
    }
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
