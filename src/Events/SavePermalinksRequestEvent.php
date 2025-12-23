<?php

namespace Sitchco\Events;

use Sitchco\BackgroundProcessing\BackgroundRequestEvent;
use Sitchco\Utils\Hooks;

/**
 * Class SavePermalinksRequestEvent
 * This class provides an asynchronous hook for handling permalink saves in WordPress. It extends the
 * WP_Async_Request class to perform a background task after permalinks are saved, triggering the
 * 'sitchco/event/after_save_permalinks' action. This can be useful when specific actions need to be taken
 * after updating permalink settings without blocking the main thread.
 * Usage Example:
 * ```php
 * use Sitchco\Events\SavePermalinksRequestEvent;
 * add_action(SavePermalinksRequestEvent::hookName(), function() {
 *     // Code to run after permalinks are saved
 * });
 * ```
 * @package Sitchco\Events
 */

class SavePermalinksRequestEvent extends BackgroundRequestEvent
{
    const HOOK_SUFFIX = 'after_save_permalinks';

    public function init(): void
    {
        add_action(Hooks::name('after_save_permalinks'), [$this, 'dispatchIfHooked']);
    }
}
