<?php

namespace Sitchco\Events;

abstract class BackgroundEvent extends \WP_Async_Request
{
    const ACTION_NAME = '';

    /**
     * @var string
     */
    protected $prefix = 'sitchco';

    public function getEventName()
    {
        return $this->action;
    }

    protected function handle(): void
    {
        do_action("$this->prefix/" . static::ACTION_NAME);
    }
}