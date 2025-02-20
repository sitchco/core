<?php

use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminColumns;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminFilters;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminSort;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeQueries;
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
    AcfPostTypeQueries::class => true,
    AcfPostTypeAdminColumns::class => true,
    AcfPostTypeAdminSort::class => true,
    AcfPostTypeAdminFilters::class => true,
];