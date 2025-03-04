<?php

namespace Sitchco\Events;

use Sitchco\Support\HasAsyncAction;
use Sitchco\Support\HasHooks;
use Sitchco\Utils\Hooks;

/**
 *
 */
abstract class BackgroundEvent extends \WP_Async_Request
{
    use HasAsyncAction;

    /**
     * @var string
     */
    protected $prefix = Hooks::ROOT;

    const HOOK_PREFIX = 'event';
}