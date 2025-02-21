<?php

namespace Sitchco\Events;

/**
 * Class SavePermalinksAsyncHook
 * This class provides an asynchronous hook for handling permalink saves in WordPress. It extends the
 * WP_Async_Request class to perform a background task after permalinks are saved, triggering the
 * 'sitchco/after_save_permalinks' action. This can be useful when specific actions need to be taken
 * after updating permalink settings without blocking the main thread.
 * Usage Example:
 * ```php
 * use Sitchco\Events\SavePermalinksAsyncHook;
 * add_action(SavePermalinksAsyncHook::hookName(), function() {
 *     // Code to run after permalinks are saved
 * });
 * ```
 * @package Sitchco\Events
 */

class SavePermalinksAsyncHook extends BackgroundEvent
{
    const HOOK_NAME = 'after_save_permalinks';

    protected $action = 'save_permalinks';

    public function __construct()
    {
        parent::__construct();
        add_action('current_screen', [$this, 'onSavePermalinks']);
    }
    public function onSavePermalinks($screen): void
    {
        if ($screen->id === 'options-permalink' && ! empty($_POST['permalink_structure'])) {
            $this->dispatch();
        }
    }
}