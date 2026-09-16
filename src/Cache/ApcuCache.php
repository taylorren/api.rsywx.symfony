<?php

namespace App\Cache;

/**
 * Direct APCu cache — no adapter layer.
 *
 * Uses APCu shared memory when the extension is available, and falls back to
 * a simple JSON file cache in cache/ otherwise (CLI, test runs, hosts without
 * apcu). Falls back silently so callers never need to care which backend is
 * active; use getStats() to introspect.
 */
class ApcuCache
{
    private const KEY_PREFIX = 'app_cache:';

    private bool $apcuAvailable;
    private string $fallbackDir;
    private int $defaultTtl;

    public function __construct(int $defaultTtl = 86400)
    {
        $this->defaultTtl = $defaultTtl; // 24 hours default
        $this->fallbackDir = dirname(__DIR__, 2) . '/cache';

        $cliApcuEnabled = PHP_SAPI !== 'cli'
            || filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN);

        $this->apcuAvailable = extension_loaded('apcu')
            && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)
            && $cliApcuEnabled;

        if (!$this->apcuAvailable && !is_dir($this->fallbackDir)) {
            @mkdir($this->fallbackDir, 0777, true);
        }
    }

    public function get(string $key): mixed
    {
        if ($this->apcuAvailable) {
            $value = apcu_fetch(self::KEY_PREFIX . $key, $success);
            return $success ? $value : null;
        }

        return $this->fallbackGet($key);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->apcuAvailable) {
            return apcu_store(self::KEY_PREFIX . $key, $value, $ttl ?? $this->defaultTtl);
        }

        return $this->fallbackSet($key, $value, $ttl ?? $this->defaultTtl);
    }

    public function delete(string $key): bool
    {
        if ($this->apcuAvailable) {
            return apcu_delete(self::KEY_PREFIX . $key);
        }

        $file = $this->fallbackPath($key);
        return !file_exists($file) || @unlink($file);
    }

    public function clear(): bool
    {
        if ($this->apcuAvailable) {
            return apcu_clear_cache();
        }

        $ok = true;
        foreach (glob($this->fallbackDir . '/*.cache.json') ?: [] as $file) {
            $ok = @unlink($file) && $ok;
        }
        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function getStats(): array
    {
        if ($this->apcuAvailable) {
            $info = apcu_cache_info(true);
            return [
                'type' => 'APCu',
                'backend' => 'APCu shared memory',
                'entries' => $info['num_entries'] ?? null,
                'mem_size' => $info['mem_size'] ?? null,
            ];
        }

        return [
            'type' => 'Filesystem fallback',
            'backend' => 'File System Cache',
            'path' => $this->fallbackDir,
        ];
    }

    private function fallbackPath(string $key): string
    {
        return $this->fallbackDir . '/' . sha1($key) . '.cache.json';
    }

    private function fallbackGet(string $key): mixed
    {
        $file = $this->fallbackPath($key);
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload) || !array_key_exists('expires', $payload)) {
            return null;
        }

        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $payload['value'];
    }

    private function fallbackSet(string $key, mixed $value, int $ttl): bool
    {
        $payload = json_encode([
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ]);

        return $payload !== false
            && @file_put_contents($this->fallbackPath($key), $payload) !== false;
    }
}
