<?php

namespace Sitchco\Events;

use Sitchco\Framework\Core\Singleton;

/**
 * Class SavePermalinksAsyncHook
 * This class provides an asynchronous hook for handling permalink saves in WordPress. It extends the
 * WP_Async_Request class to perform a background task after permalinks are saved, triggering the
 * 'sitchco/after_save_permalinks' action. This can be useful when specific actions need to be taken
 * after updating permalink settings without blocking the main thread.
 * Usage Example:
 * ```php
 * use Sitchco\Events\SavePermalinksAsyncHook;
 * add_action(SavePermalinksAsyncHook::ACTION, function() {
 *     // Code to run after permalinks are saved
 * });
 * ```
 * @package Sitchco\Events
 */

class SavePermalinksAsyncHook extends \WP_Async_Request
{
    use Singleton;
    
    const ACTION = 'sitchco/after_save_permalinks';
    /**
     * @var string
     */
    protected $prefix = 'sitchco';

    /**
     * @var string
     */
    protected $action = 'onSavePermalinks';

    protected function __construct()
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
    protected function handle(): void
    {
        do_action(static::ACTION);
    }
}