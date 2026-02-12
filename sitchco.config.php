<?php

use Sitchco\Modules\AcfLifecycle;
use Sitchco\Modules\AdminTools;
use Sitchco\Modules\AdvancedCustomFields\AcfOptions;
use Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminColumns;
use Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminFilters;
use Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminSort;
use Sitchco\Modules\AdvancedCustomFields\AcfPostTypeQueries;
use Sitchco\Modules\AmazonCloudfront;
use Sitchco\Modules\BackgroundProcessing;
use Sitchco\Modules\CacheInvalidation\CacheInvalidation;
use Sitchco\Modules\Cron;
use Sitchco\Modules\Flash;
use Sitchco\Modules\Imagify;
use Sitchco\Modules\Model\ImageModel;
use Sitchco\Modules\Model\PostModel;
use Sitchco\Modules\Model\TermModel;
use Sitchco\Modules\PageOrder;
use Sitchco\Modules\PostDeployment;
use Sitchco\Modules\PostLifecycle;
use Sitchco\Modules\Stream;
use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Modules\Wordpress\BlockConfig;
use Sitchco\Modules\Wordpress\Cleanup;
use Sitchco\Modules\Wordpress\SearchRewrite;
use Sitchco\Modules\Wordpress\SvgUpload;
use Sitchco\Modules\WPRocket;
use Sitchco\Modules\YoastSEO;

return [
    'container' => [],
    'modules' => [
        Cleanup::class,
        SearchRewrite::class,
        BackgroundProcessing::class,
        Cron::class,
        PostModel::class,
        TermModel::class,
        ImageModel::class,
        AcfPostTypeQueries::class,
        AcfPostTypeAdminColumns::class,
        AcfPostTypeAdminSort::class,
        AcfPostTypeAdminFilters::class,
        AcfOptions::class,
        WPRocket::class,
        YoastSEO::class,
        Imagify::class,
        Stream::class,
        Flash::class,
        AmazonCloudfront::class,
        SvgUpload::class,
        BlockConfig::class,
        UIFramework::class,
        PageOrder::class,
        AdminTools::class,
        SvgSprite::class,
        PostDeployment::class,
        AcfLifecycle::class,
        PostLifecycle::class,
        CacheInvalidation::class,
    ],
    'disallowedBlocks' => [],
];
