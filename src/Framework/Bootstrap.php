<?php

namespace Sitchco\Framework;

use Sitchco\Events\SavePermalinksAsyncHook;
use Sitchco\Framework\Config\JsonConfig;
use Sitchco\Framework\Core\Registry;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;

class Bootstrap
{
    protected array $modules = [
        Cleanup::class,
        SearchRewrite::class,
    ];
    public function __construct()
    {
        new JsonConfig(Registry::add($this->modules));
        SavePermalinksAsyncHook::init();
    }
}