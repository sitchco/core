<?php

namespace Sitchco\Events;

use Sitchco\Support\HasAsyncAction;
use Sitchco\Utils\Hooks;

/**
 *
 */
abstract class BackgroundQueue extends \WP_Background_Process
{
    use HasAsyncAction;

    /**
     * @var string
     */
    protected $prefix = Hooks::ROOT;

    const HOOK_PREFIX = 'queue';
}