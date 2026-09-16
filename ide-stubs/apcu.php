<?php

/**
 * IDE stubs for the APCu extension (Intelephense does not bundle apcu signatures).
 *
 * This file is intentionally OUTSIDE the composer autoload paths — it is only
 * indexed by the IDE. The `function_exists` guards make it harmless even if
 * it ever does get included at runtime.
 *
 * @see https://www.php.net/manual/en/book.apcu.php
 */

namespace {

    if (!class_exists('APCuIterator')) {
        /**
         * @implements Iterator<string, mixed>
         */
        class APCuIterator implements Iterator
        {
            /** @param string|array|null $search */
            public function __construct($search = null, int $format = 0, ?string $chunk_size = null, int $list_size = 0) {}

            #[\ReturnTypeWillChange]
            public function rewind(): void {}

            #[\ReturnTypeWillChange]
            public function current()
            {
                return false;
            }

            #[\ReturnTypeWillChange]
            public function key()
            {
                return null;
            }

            #[\ReturnTypeWillChange]
            public function next(): void {}

            #[\ReturnTypeWillChange]
            public function valid(): bool
            {
                return false;
            }
        }
    }

    if (!function_exists('apcu_fetch')) {
        /**
         * @param string $key
         * @param-out bool $success
         * @return mixed|false The stored value, or false on failure
         */
        function apcu_fetch(string $key, ?bool &$success = null)
        {
            $success = false;
            return false;
        }
    }

    if (!function_exists('apcu_store')) {
        /**
         * @param string|array $key
         * @param mixed $var
         * @psalm-param int|null $ttl
         */
        function apcu_store($key, $var = null, int $ttl = 0): bool
        {
            return false;
        }
    }

    if (!function_exists('apcu_delete')) {
        /**
         * @param string|array|APCuIterator $key
         */
        function apcu_delete($key): bool
        {
            return false;
        }
    }

    if (!function_exists('apcu_clear_cache')) {
        function apcu_clear_cache(): bool
        {
            return false;
        }
    }

    if (!function_exists('apcu_exists')) {
        /**
         * @param string|array $key
         * @return bool|array
         */
        function apcu_exists($key)
        {
            return false;
        }
    }

    if (!function_exists('apcu_cache_info')) {
        /**
         * @param bool $limited
         * @return array|false
         */
        function apcu_cache_info(bool $limited = false)
        {
            return false;
        }
    }

    if (!function_exists('apcu_add')) {
        function apcu_add(string $key, mixed $var = null, int $ttl = 0): bool
        {
            return false;
        }
    }

    if (!function_exists('apcu_inc')) {
        function apcu_inc(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): int|false
        {
            $success = false;
            return false;
        }
    }

    if (!function_exists('apcu_dec')) {
        function apcu_dec(string $key, int $step = 1, ?bool &$success = null, int $ttl = 0): int|false
        {
            $success = false;
            return false;
        }
    }

    if (!function_exists('apcu_entry')) {
        /**
         * @param callable(string $key, mixed $value): mixed $generator
         * @return mixed
         */
        function apcu_entry(string $key, callable $generator, int $ttl = 0)
        {
            return $generator($key, apcu_fetch($key));
        }
    }

    if (!function_exists('apcu_sma_info')) {
        function apcu_sma_info(bool $limited = false): array|false
        {
            return false;
        }
    }
}
