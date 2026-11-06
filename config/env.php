<?php

/**
 * Shared environment loader for every entry point (web app + test bootstrap).
 *
 * PHPUnit's phpunit.xml interpolates `${VAR}` placeholders; when the underlying
 * variable is absent from the process environment the literal "${VAR}" string
 * ends up in $_ENV and blocks Dotenv from filling the real value. Clear any
 * such placeholders first, then load .env (immutable: real host variables win).
 */

require_once __DIR__ . '/../vendor/autoload.php';

foreach (array_keys($_ENV) as $key) {
    if (is_string($_ENV[$key]) && preg_match('/^\$\{.+\}$/', $_ENV[$key])) {
        unset($_ENV[$key]);
        putenv($key);
    }
}

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
