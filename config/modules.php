<?php

use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminColumns;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminFilters;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminSort;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeQueries;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;
use Sitchco\Integration\WPRocket;
use Sitchco\Integration\YoastSEO;
use Sitchco\Integration\Imagify;
use Sitchco\Integration\Stream;
use Sitchco\Flash\Flash;
use Sitchco\Integration\AmazonCloudfront;
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
    WPRocket::class => true,
    YoastSEO::class => true,
    Imagify::class => true,
    Stream::class => true,
    Flash::class => true,
    AmazonCloudfront::class => true,
];