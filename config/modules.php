<?php

use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Timber;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;

return [
    Cleanup::class => true,
    SearchRewrite::class => true,
    BackgroundEventManager::class => true,
    Timber::class => true
];