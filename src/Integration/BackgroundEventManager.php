<?php

namespace Sitchco\Integration;

use Sitchco\Events\SavePermalinksAsyncHook;
use Sitchco\Framework\Core\AbstractModule;

class BackgroundEventManager extends AbstractModule
{
    public const NAME = 'background_event_manager';
    public const CATEGORY = 'core';
    public const DEFAULT = true;
    public const FEATURES = [
       'savePermalinks'
    ];

    public function savePermalinks()
    {
        SavePermalinksAsyncHook::init();
    }
}