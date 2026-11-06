<?php

namespace App\Config;

/**
 * Small typed accessor over the process environment ($_ENV / getenv).
 *
 * Centralises the values that used to be hard-coded across models/controllers
 * (cover URLs, blog URL, geolocation, cache directory, environment name) so
 * they can be changed without touching code.
 */
final class Config
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? null;
        if ($value === null || $value === '') {
            $envValue = getenv($key);
            $value = ($envValue === false || $envValue === '') ? null : $envValue;
        }

        return $value ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isDevelopment(): bool
    {
        return self::get('APP_ENV', 'production') === 'development';
    }

    public static function coversBaseUrl(): string
    {
        return rtrim(self::get('COVERS_BASE_URL', 'https://api.rsywx.com/covers') ?? '', '/');
    }

    /**
     * Build the public cover URL for a book id (e.g. "00666").
     */
    public static function coverUri(string $bookid): string
    {
        return self::coversBaseUrl() . '/' . rawurlencode($bookid) . '.jpg';
    }

    public static function blogUrl(): string
    {
        return rtrim(self::get('WP_BLOG_URL', 'https://blog.rsywx.net') ?? '', '/');
    }

    public static function cacheDir(): string
    {
        return self::get('CACHE_DIR', dirname(__DIR__, 2) . '/cache') ?? '';
    }

    public static function geolocationEnabled(): bool
    {
        return self::bool('GEOLOCATION_ENABLED', true);
    }

    public static function geolocationApiUrl(): string
    {
        return rtrim(self::get('GEOLOCATION_API_URL', 'http://ip-api.com/json') ?? '', '/');
    }

    /**
     * Whether GET /books/{id} records a visit by default. Can be overridden
     * per request with ?visit=true|false (or ?record_visit=...).
     */
    public static function recordVisitsByDefault(): bool
    {
        return self::bool('RECORD_VISITS', true);
    }
}
