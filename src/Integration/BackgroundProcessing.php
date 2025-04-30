<?php

namespace Sitchco\Integration;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Events\SavePostQueueEvent;
use Sitchco\Framework\Core\Module;
use Sitchco\Integration\BackgroundProcessing\BackgroundActionQueue;
use Sitchco\Integration\BackgroundProcessing\BackgroundQueueEvent;
use Sitchco\Integration\BackgroundProcessing\BackgroundRequestEvent;
use Sitchco\Utils\Hooks;

class BackgroundProcessing extends Module
{
    public const FEATURES = [
        'savePermalinksRequestEvent',
        'savePostQueueEvent',
        'processPostsAfterSavePermalinks'
    ];

    protected Container $Container;

    protected BackgroundActionQueue $Queue;

    /**
     * @var BackgroundRequestEvent[]
     */
    protected array $request_events;

    /**
     * @var BackgroundQueueEvent[]
     */
    protected array $queue_events;

    /**
     * @param Container $Container
     */
    public function __construct(BackgroundActionQueue $Queue, Container $Container)
    {
        $this->Queue = $Queue;
        $this->Container = $Container;
    }

    public function init(): void
    {
        add_action('current_screen', function($screen) {
            if ($screen->id === 'options-permalink' && ! empty($_POST['permalink_structure'])) {
                do_action(Hooks::name('after_save_permalinks'), $screen);
            }
        });
        add_action('shutdown', function() {
            do_action(Hooks::name('save_background_queue'));
        });
        add_action(Hooks::name('save_background_queue'), function() {
            if (!$this->Queue->hasQueuedItems()) {
                return;
            }
            $this->Queue->save()->dispatch();
        });
    }

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function savePermalinksRequestEvent(): void
    {
        $event = $this->Container->get(SavePermalinksRequestEvent::class);
        $event->init();
        $this->request_events[$event::class] = $event;
    }

    public function savePostQueueEvent(): void
    {
        $event = $this->Container->get(SavePostQueueEvent::class);
        $event->init();
        $this->queue_events[$event::class] = $event;
    }
}