<?php

namespace Sitchco\Integration;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Sitchco\Events\SavePermalinksAsyncHook;
use Sitchco\Events\SavePostAsyncHook;
use Sitchco\Framework\Core\Module;
use WP_Async_Request;

class BackgroundEventManager extends Module
{
    public const FEATURES = [
        'savePermalinks',
        'savePost',
    ];

    protected Container $Container;

    /**
     * @var WP_Async_Request[]
     */
    protected array $events;

    /**
     * @param Container $Container
     */
    public function __construct(Container $Container)
    {
        $this->Container = $Container;
    }

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function savePermalinks(): void
    {
        $event = $this->Container->get(SavePermalinksAsyncHook::class);
        $this->events[$event::class] = $event;
    }

    public function savePost(): void
    {
        $event = $this->Container->get(SavePostAsyncHook::class);
        $this->events[$event::class] = $event;
    }
}