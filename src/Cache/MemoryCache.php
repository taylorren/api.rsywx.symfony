<?php

namespace App\Cache;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Exception;

/**
 * MemoryCache class using Symfony Cache with APCu support
 * 
 * Uses Symfony's ApcuAdapter when available, falls back to FilesystemAdapter
 */
class MemoryCache
{
    private $cache;
    private $defaultTtl;

    public function __construct($defaultTtl = 86400)
    {
        $this->defaultTtl = $defaultTtl; // 24 hours default

        // Use Symfony's ApcuAdapter if available, otherwise FilesystemAdapter
        try {
            $this->cache = new ApcuAdapter('app_cache', $defaultTtl);
        } catch (Exception $e) {
            // Fallback to filesystem cache
            $this->cache = new FilesystemAdapter('app_cache', $defaultTtl, __DIR__ . '/../../cache');
        }
    }

    public function get($key)
    {
        try {
            $item = $this->cache->getItem($key);
            return $item->isHit() ? $item->get() : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function set($key, $value, $ttl = null)
    {
        try {
            $item = $this->cache->getItem($key);
            $item->set($value);

            if ($ttl !== null) {
                $item->expiresAfter($ttl);
            }

            return $this->cache->save($item);
        } catch (Exception $e) {
            return false;
        }
    }

    public function delete($key)
    {
        try {
            return $this->cache->deleteItem($key);
        } catch (Exception $e) {
            return false;
        }
    }

    public function clear()
    {
        try {
            return $this->cache->clear();
        } catch (Exception $e) {
            return false;
        }
    }

    public function has($key)
    {
        try {
            return $this->cache->hasItem($key);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getStats()
    {
        // Determine which adapter is being used
        $adapterClass = get_class($this->cache);

        if (strpos($adapterClass, 'ApcuAdapter') !== false) {
            return [
                'type' => 'Symfony ApcuAdapter',
                'adapter_class' => $adapterClass,
                'backend' => 'APCu Memory Cache'
            ];
        } else {
            return [
                'type' => 'Symfony FilesystemAdapter',
                'adapter_class' => $adapterClass,
                'backend' => 'File System Cache'
            ];
        }
    }

    public function getCacheDir()
    {
        $adapterClass = get_class($this->cache);

        if (strpos($adapterClass, 'ApcuAdapter') !== false) {
            return 'symfony://apcu';
        } else {
            return __DIR__ . '/../../cache';
        }
    }
}
