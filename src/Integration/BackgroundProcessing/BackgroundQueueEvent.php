<?php

namespace Sitchco\Integration\BackgroundProcessing;

use Sitchco\Support\HasHooks;
use Sitchco\Utils\Hooks;

/**
 *
 */
abstract class BackgroundQueueEvent
{
    use HasHooks;

    const HOOK_PREFIX = BackgroundActionQueue::HOOK_SUFFIX;

    protected BackgroundActionQueue $Queue;

    public function __construct(BackgroundActionQueue $Queue)
    {
        $this->Queue = $Queue;
    }

    protected function enqueue(array $args, array $sub_actions = []): void
    {
        $this->Queue->queueAction(static::HOOK_SUFFIX, $args, $sub_actions);
    }

    abstract public function init();
}