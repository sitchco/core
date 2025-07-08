<?php

declare(strict_types=1);

namespace Sitchco\Utils;

use Closure;

/**
 * Class Cache
 * @package Sitchco\Utils
 */
class Cache
{
    //    public static function clearCache(): void
    //    {
    //        CacheController::flagCacheClear();
    //    }

    /**
     * Retrieves a cached value or stores it if not found.
     *
     * @param string  $transient  The cache key.
     * @param int     $expiration Cache duration in seconds.
     * @param Closure $callback   Callback function to generate the value.
     *
     * @return mixed The cached value.
     */
    public static function rememberTransient(string $transient, int $expiration, Closure $callback): mixed
    {
        if ($value = get_transient($transient)) {
            return $value;
        }

        $value = $callback();
        set_transient($transient, $value, $expiration);

        return $value;
    }
}
