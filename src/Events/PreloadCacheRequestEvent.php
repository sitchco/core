<?php

namespace Sitchco\Events;

use Sitchco\BackgroundProcessing\BackgroundRequestEvent;
use Sitchco\Utils\Hooks;

/**
 * Class PreloadCacheRequestEvent
 * This class provides an asynchronous hook for handling  in WordPress. It extends the
 * WP_Async_Request class to perform a background task after permalinks are saved, triggering the
 * 'sitchco/event/preload_cache' action. This can be useful when specific actions need to be taken
 * after updating permalink settings without blocking the main thread.
 * Usage Example:
 * ```php
 * use Sitchco\Events\PreloadCacheRequestEvent;
 * add_action(PreloadCacheRequestEvent::hookName(), function() {
 *     // Code to run after permalinks are saved
 * });
 * ```
 * @package Sitchco\Events
 */

class PreloadCacheRequestEvent extends BackgroundRequestEvent
{
    const HOOK_SUFFIX = 'preload_cache';

    protected array $data_keys = ['callback'];

    public function init(): void
    {
        add_action(Hooks::name('preload_cache'), function (
            string $callback,
            string $cache_set,
            string $cache_key,
            array $cache_set_args,
        ) {
            $this->data(compact('callback', 'cache_set', 'cache_key', 'cache_set_args'));
            $this->dispatchIfHooked();
        });
    }
}
