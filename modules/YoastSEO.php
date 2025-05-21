<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

/**
 * Class YoastSEO
 * @package Sitchco\Integration
 */
class YoastSEO extends Module
{
    const FEATURES = [
        'proDashboardInactive'
    ];

    public function init(): void
    {
        add_filter('wpseo_metabox_prio', fn(): string => 'low');
        add_filter('wpseo_premium_post_redirect_slug_change', '__return_true');
        add_filter('Yoast\WP\SEO\post_redirect_slug_change', '__return_true');
        add_filter('wpseo_sitemap_content_before_parse_html_images', '__return_empty_string');
    }

    /**
     * This disables various yoast features that require a connection
     * to yoast pro dashboard
     */
    public function proDashboardInactive(): void
    {
        add_filter('Yoast\WP\SEO\prominent_words_post_types', '__return_empty_array');
    }
}
