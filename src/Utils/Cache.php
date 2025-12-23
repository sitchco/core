<?php

declare(strict_types=1);

namespace Sitchco\Utils;

use Sitchco\Events\PreloadCacheRequestEvent;

/**
 * Unified caching utility with three persistence tiers:
 *
 * 1. Object Cache (wp_cache_*) - Volatile, fastest
 *    - Lost on cache flush
 *    - Uses Redis/Memcached in production, in-memory in local
 *    - Good for: configs, manifests, regeneratable performance data
 *
 * 2. Transients (*_transient) - Medium persistence
 *    - Can be lost on cache flush if Redis Object Cache is active
 *    - DB-backed otherwise
 *    - Good for: time-based data, API responses
 *
 * 3. Options (*_option) - Ultra-persistent
 *    - Survives cache flushes (DB-backed)
 *    - Supports optional TTL with metadata
 *    - Good for: system state, feature flags, licenses
 *
 * @package Sitchco\Utils
 */
class Cache
{
    private static function preload(
        callable $callback,
        int $stale,
        int|false|null $timeout,
        callable $cache_set_callback,
        string $cache_set_key,
        array $cache_set_args,
    ): void {
        if (!$stale || false === $timeout || time() + $stale >= $timeout) {
            return;
        }
        if (!is_callable($callback, false, $callable_name)) {
            error_log(
                'Provided callback function cannot be preloaded because it must be statically referenced',
                E_USER_WARNING,
            );
            return;
        }
        if (!is_callable($cache_set_callback, false, $cache_set_callable_name)) {
            error_log(
                'Provided cache set callback function cannot be used because it must be statically referenced',
                E_USER_WARNING,
            );
            return;
        }
        do_action(
            Hooks::name('preload_cache'),
            $callable_name,
            $cache_set_callable_name,
            $cache_set_key,
            $cache_set_args,
        );
    }

    private static function maybePreloadWPCache(string $key, callable $callback, ?int $stale, array $args): void
    {
        if (!$stale) {
            return;
        }
        $timeout = wp_cache_get($key . '_timeout', $args[1]);
        static::preload($callback, $stale, $timeout, [static::class, 'set'], $key, $args);
    }

    private static function maybePreloadTransient(string $key, callable $callback, ?int $stale, array $args): void
    {
        if (!$stale) {
            return;
        }
        if (wp_using_ext_object_cache() || wp_installing()) {
            static::maybePreloadWPCache($key, $callback, $stale, [...$args, 'transient']);
            return;
        }
        $timeout = get_option('_transient_timeout_' . $key);
        static::preload($callback, $stale, $timeout, 'set_transient', $key, $args);
    }

    public static function set(string $key, mixed $value, int $ttl = DAY_IN_SECONDS, string $group = 'sitchco'): bool
    {
        wp_cache_set($key, $value, $group, $ttl);
        return wp_cache_set($key . '_timeout', time() + $ttl, $group, $ttl);
    }

    public static function setOption(string $key, mixed $value, ?int $expiration = null): bool
    {
        // Wrap with metadata if expiration is set
        $stored =
            $expiration !== null
                ? [
                    'value' => $value,
                    '__cache_meta' => [
                        'expires_at' => time() + $expiration,
                        'created_at' => time(),
                    ],
                ]
                : $value;

        return update_option($key, $stored, false); // false = don't autoload
    }

    /**
     * Remember a value using object cache (fastest, volatile).
     * Lost on cache flush. Good for performance-critical regeneratable data.
     *
     * @param string   $key      Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int      $ttl      Time-to-live in seconds
     * @param string   $group    Cache group for organization
     * @return mixed The cached or freshly generated value
     */
    public static function remember(
        string $key,
        callable $callback,
        int $ttl = DAY_IN_SECONDS,
        ?int $stale = null,
        string $group = 'sitchco',
    ): mixed {
        $cached = wp_cache_get($key, $group);
        if ($cached !== false) {
            static::maybePreloadWPCache($key, $callback, $stale, [$ttl, $group]);
            return $cached;
        }

        $value = $callback();
        static::set($key, $value, $ttl, $group);
        return $value;
    }

    /**
     * Forget (delete) a value from object cache.
     *
     * @param string $key   Cache key
     * @param string $group Cache group
     * @return bool True on success, false on failure
     */
    public static function forget(string $key, string $group = 'sitchco'): bool
    {
        wp_cache_delete($key . '_timeout', $group);
        return wp_cache_delete($key, $group);
    }

    /**
     * Remember a value using transients (medium persistence).
     * Can be lost on cache flush if Redis Object Cache is active.
     * Use for time-based data that can be regenerated.
     *
     * @param string   $key        Transient key
     * @param callable $callback   Function to generate value if not cached
     * @param int      $expiration Cache duration in seconds
     * @return mixed The cached or freshly generated value
     */
    public static function rememberTransient(
        string $key,
        callable $callback,
        int $expiration = DAY_IN_SECONDS,
        ?int $stale = null,
    ): mixed {
        $value = get_transient($key);
        if ($value !== false) {
            static::maybePreloadTransient($key, $callback, $stale, [$expiration]);
            return $value;
        }

        $value = $callback();
        set_transient($key, $value, $expiration);

        return $value;
    }

    /**
     * Forget (delete) a transient.
     *
     * @param string $key Transient key
     * @return bool True on success, false on failure
     */
    public static function forgetTransient(string $key): bool
    {
        return delete_transient($key);
    }

    /**
     * Remember a value using options (ultra-persistent, survives cache flushes).
     * Use for system state, feature flags, licenses, etc.
     *
     * @param string      $key        Option key
     * @param callable    $callback   Function to generate value if not cached
     * @param int|null    $expiration Optional TTL in seconds. Null = never expires
     * @return mixed The cached or freshly generated value
     */
    public static function rememberOption(
        string $key,
        callable $callback,
        ?int $expiration = null,
        ?int $stale = null,
    ): mixed {
        $cached = get_option($key);

        if ($cached !== false) {
            // Check if this is a TTL-wrapped value
            if (!(is_array($cached) && isset($cached['__cache_meta']))) {
                // Simple value without TTL metadata
                return $cached;
            }
            $expiresAt = $cached['__cache_meta']['expires_at'] ?? null;
            if ($expiresAt === null || time() < $expiresAt) {
                static::preload($callback, $stale, $expiresAt, [static::class, 'setOption'], $key, [$expiration]);
                return $cached['value'];
            }
            // Expired, fall through to regenerate
        }

        $value = $callback();

        static::setOption($key, $value, $expiration);

        return $value;
    }

    /**
     * Forget (delete) an option.
     *
     * @param string $key Option key
     * @return bool True on success, false on failure
     */
    public static function forgetOption(string $key): bool
    {
        return delete_option($key);
    }
}
