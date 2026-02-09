<?php

namespace Sitchco\BackgroundProcessing;

use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Events\SavePostQueueEvent;
use Sitchco\Support\HookName;
use Sitchco\Utils\Hooks;

/**
 * BackgroundQueue is a shared background process for simple tasks that are processed via action hooks,
 * similar to wp_schedule_event(). In most cases, items will be pushed into the queue at a specific, predetermined
 * time during execution, such as after a post is saved or ofter saving the permalinks admin form.
 * Items will only be queued if there are any tasks added to process them.
 *
 * Trigger Usage
 * $Queue->queueAction('action_name', ['some_arg' => $value])
 * Processing Usage:
 * $Queue->addTask('action_name', 'my_callback_function')
 */
class BackgroundActionQueue extends \WP_Background_Process
{
    public const HOOK_SUFFIX = 'background_queue';

    protected $action = self::HOOK_SUFFIX;

    /**
     * @var string
     */
    protected $prefix = HookName::ROOT;

    protected array|\WP_Error $dispatch_response;

    /**
     * @param array $data {
     *  @type string $action name of hook to fire when processed
     *  @type array $args additional arguments to be stored and passed to the action hook
     * }
     * @return BackgroundActionQueue
     */
    public function push_to_queue($data): static
    {
        if (!is_array($data)) {
            $data = ['args' => [$data]];
        }
        $data = wp_parse_args($data, [
            'action' => 'default',
            'args' => [],
        ]);
        return $this->queueAction($data['action'], $data['args']);
    }

    /**
     * Push action into queue conditionally, based on whether any callbacks have been registered for that hook
     *
     * @param string $action name of hook to fire when processed
     * @param array $args additional arguments to be stored and passed to the action hook
     * @param array $sub_actions list of sub-action names to also be queued
     * @return $this
     */
    public function queueAction(string $action, array $args = [], array $sub_actions = []): static
    {
        if (!has_action(Hooks::name(static::HOOK_SUFFIX, $action))) {
            return $this;
        }
        foreach ($sub_actions as $sub_action) {
            $this->queueAction(HookName::join(static::HOOK_SUFFIX, $sub_action), $args);
        }
        return parent::push_to_queue(compact('action', 'args'));
    }

    /**
     * Adds a callback to be executed whenever queued items with the given action are processed
     *
     * @param string $action
     * @param callable $task_callback
     * @param int $priority
     * @return void
     */
    public function addTask(string $action, callable $task_callback, int $priority = 10): void
    {
        add_action(Hooks::name(static::HOOK_SUFFIX, $action), $task_callback, $priority);
    }

    public function addBulkPostsTask(
        string $trigger_action,
        string $task_action,
        callable $task_callback,
        int $priority = 10,
        array $query_args = [],
    ): void {
        add_action($trigger_action, function () use ($query_args, $task_action, $task_callback, $priority) {
            $query = wp_parse_args($query_args, [
                'post_type' => 'post',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);
            $posts = get_posts($query);
            $this->addTask($task_action, $task_callback, $priority);
            foreach ($posts as $post) {
                do_action(Hooks::name(static::HOOK_SUFFIX, 'bulk_posts'), $post);
            }
        });
    }

    public function addSavePermalinksBulkSavePostsTask(
        callable $task_callback,
        int $priority = 10,
        array $query_args = [],
    ): void {
        $this->addBulkPostsTask(
            SavePermalinksRequestEvent::hookName(),
            SavePostQueueEvent::HOOK_SUFFIX,
            $task_callback,
            $priority,
            $query_args,
        );
    }

    public function hasQueuedItems(): bool
    {
        return count($this->data) > 0;
    }

    public function getQueuedItems(): array
    {
        return $this->data;
    }

    protected function task($item)
    {
        // Hook: sitchco/background_queue/{action}
        do_action(Hooks::name(static::HOOK_SUFFIX, $item['action']), $item['args']);
        return false;
    }

    public function dispatch(): array|\WP_Error
    {
        $this->dispatch_response = parent::dispatch();
        return $this->dispatch_response;
    }

    public function getDispatchResponse(): \WP_Error|array
    {
        return $this->dispatch_response;
    }
}
