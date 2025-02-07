<?php

use Sitchco\Integration\AdvancedCustomFields\CustomPostTypes;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;
use Sitchco\Model\PostModel;
use Sitchco\Model\TermModel;

return [
    Cleanup::class => true,
    SearchRewrite::class => true,
    BackgroundEventManager::class => true,
    PostModel::class => true,
    TermModel::class => true,
    CustomPostTypes::class => true,
];