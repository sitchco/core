<?php

namespace Sitchco\Integration\BackgroundProcessing;

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
    public const HOOK_NAME = 'background_queue';

    protected $action = self::HOOK_NAME;

    /**
     * @var string
     */
    protected $prefix = Hooks::ROOT;

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
            'args' => []
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
        if (!has_action(Hooks::name(static::HOOK_NAME, $action))) {
            return $this;
        }
        foreach ($sub_actions as $sub_action) {
            $this->queueAction(Hooks::join(static::HOOK_NAME, $sub_action), $args);
        }
        return parent::push_to_queue(compact('action', 'args'));
    }

    /**
     * Adds a callback to be executed whenever queued items with the given action are processed
     *
     * @param string $action
     * @param callable $task_callback
     * @param int $priority
     * @param int $accepted_args
     * @return void
     */
    public function addTask(string $action, callable $task_callback, int $priority = 10): void
    {
        add_action(Hooks::name(static::HOOK_NAME, $action), $task_callback, $priority);
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
        do_action(Hooks::name(static::HOOK_NAME, $item['action']), $item['args']);
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