<?php

declare(strict_types=1);

namespace Sitchco\Utils;

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
        string $group = 'sitchco',
    ): mixed {
        $cached = wp_cache_get($key, $group);
        if ($cached !== false) {
            return $cached;
        }

        $value = $callback();
        wp_cache_set($key, $value, $group, $ttl);

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
    public static function rememberTransient(string $key, callable $callback, int $expiration = DAY_IN_SECONDS): mixed
    {
        $value = get_transient($key);
        if ($value !== false) {
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
    public static function rememberOption(string $key, callable $callback, ?int $expiration = null): mixed
    {
        $cached = get_option($key);

        if ($cached !== false) {
            // Check if this is a TTL-wrapped value
            if (is_array($cached) && isset($cached['__cache_meta'])) {
                $expiresAt = $cached['__cache_meta']['expires_at'] ?? null;
                if ($expiresAt === null || time() < $expiresAt) {
                    return $cached['value'];
                }
                // Expired, fall through to regenerate
            } else {
                // Simple value without TTL metadata
                return $cached;
            }
        }

        $value = $callback();

        // Wrap with metadata if expiration is set
        if ($expiration !== null) {
            $stored = [
                'value' => $value,
                '__cache_meta' => [
                    'expires_at' => time() + $expiration,
                    'created_at' => time(),
                ],
            ];
        } else {
            $stored = $value;
        }

        update_option($key, $stored, false); // false = don't autoload

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
