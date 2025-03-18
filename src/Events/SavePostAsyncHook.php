<?php

namespace Sitchco\Events;

/**
 * Class SavePostAsyncHook
 * This class provides an asynchronous hook after a post is saved in WordPress. It extends the
 * WP_Async_Request class to perform a background task on the ACF hook 'acf/save_post', triggering the
 * 'sitchco/background_event/after_save_post' action. This can be useful when specific actions need to be taken
 * after updating a post without blocking the main thread.
 * Usage Example:
 * ```php
 * use Sitchco\Events\SavePostAsyncHook;
 * add_action(SavePostAsyncHook::hookName(), function(int $post_id) {
 *     // Code to run after post is saved
 * });
 * ```
 * Optional secondary hook 'sitchco/background_event/after_save_post/{post_type}' to only trigger on post saves of the specified post type:
 * ```php
 *  use Sitchco\Events\SavePostAsyncHook;
 *  add_action(SavePostAsyncHook::hookName('page'), function(int $post_id) {
 *      // Code to run after page is saved
 *  });
 *  ```
 *
 * @package Sitchco\Events
 */

class SavePostAsyncHook extends BackgroundEvent
{
    const HOOK_NAME = 'after_save_post';

    protected $action = 'save_post';

    protected array $data_keys = ['post_id'];

    public function __construct()
    {
        parent::__construct();
        add_action('acf/save_post', [$this, 'onSavePost']);
    }
    public function onSavePost(int $post_id): void
    {
        $this->data(compact('post_id'));
        $this->dispatch();
    }

    protected function handle(): void
    {
        parent::handle();
        $post_type = get_post_type($this->data['post_id']);
        // Hook: sitchco/background_event/after_save_post/{post_type}
        do_action(static::hookName($post_type), ...$this->action_data);
    }
}