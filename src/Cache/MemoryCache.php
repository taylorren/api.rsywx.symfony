<?php

namespace App\Cache;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Throwable;

/**
 * MemoryCache class using Symfony Cache with APCu support
 *
 * Uses Symfony's ApcuAdapter when available, falls back to FilesystemAdapter
 */
class MemoryCache
{
    private AdapterInterface $cache;
    private int $defaultTtl;

    public function __construct(int $defaultTtl = 86400)
    {
        $this->defaultTtl = $defaultTtl; // 24 hours default

        // Use Symfony's ApcuAdapter if available, otherwise FilesystemAdapter
        try {
            $this->cache = new ApcuAdapter('app_cache', $defaultTtl);
        } catch (Throwable) {
            // Fallback to filesystem cache
            $this->cache = new FilesystemAdapter('app_cache', $defaultTtl, __DIR__ . '/../../cache');
        }
    }

    public function get(string $key): mixed
    {
        try {
            $item = $this->cache->getItem($key);
            return $item->isHit() ? $item->get() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $item = $this->cache->getItem($key);
            $item->set($value);

            if ($ttl !== null) {
                $item->expiresAfter($ttl);
            }

            return $this->cache->save($item);
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return $this->cache->deleteItem($key);
        } catch (Throwable) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            return $this->cache->clear();
        } catch (Throwable) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        try {
            return $this->cache->hasItem($key);
        } catch (Throwable) {
            return false;
        }
    }

    public function getStats(): array
    {
        $adapterClass = get_class($this->cache);

        if (str_contains($adapterClass, 'ApcuAdapter')) {
            return [
                'type' => 'Symfony ApcuAdapter',
                'adapter_class' => $adapterClass,
                'backend' => 'APCu Memory Cache'
            ];
        }

        return [
            'type' => 'Symfony FilesystemAdapter',
            'adapter_class' => $adapterClass,
            'backend' => 'File System Cache'
        ];
    }

    public function getCacheDir(): string
    {
        $adapterClass = get_class($this->cache);

        if (str_contains($adapterClass, 'ApcuAdapter')) {
            return 'symfony://apcu';
        }

        return __DIR__ . '/../../cache';
    }
}
