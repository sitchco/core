<?php

use Sitchco\Flash\Flash;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminColumns;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminFilters;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminSort;
use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeQueries;
use Sitchco\Integration\AmazonCloudfront;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Imagify;
use Sitchco\Integration\Stream;
use Sitchco\Integration\Wordpress\AllowedBlocksResolver;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;
use Sitchco\Integration\Wordpress\SvgUpload;
use Sitchco\Integration\WPRocket;
use Sitchco\Integration\YoastSEO;
use Sitchco\Model\ImageModel;
use Sitchco\Model\PostModel;
use Sitchco\Model\TermModel;

return [
    'container' => [],
    'modules' => [
        Cleanup::class,
        SearchRewrite::class,
        BackgroundEventManager::class,
        PostModel::class,
        TermModel::class,
        ImageModel::class,
        AcfPostTypeQueries::class,
        AcfPostTypeAdminColumns::class,
        AcfPostTypeAdminSort::class,
        AcfPostTypeAdminFilters::class,
        WPRocket::class,
        YoastSEO::class,
        Imagify::class,
        Stream::class,
        Flash::class,
        AmazonCloudfront::class,
        SvgUpload::class,
        AllowedBlocksResolver::class,
    ]
];
