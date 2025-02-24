<?php

namespace Sitchco\Integration\Yoast;

use Sitchco\Framework\Core\Module;

/**
 * Class YoastSEO
 * @package Sitchco\Integration\Yoast
 */
class YoastSEO extends Module
{
    const FEATURES = [
        'prominentWords'
    ];

    public function init(): void
    {
        add_filter('wpseo_metabox_prio', fn(): string => 'low');
        add_filter('wpseo_premium_post_redirect_slug_change', '__return_true');
        add_filter('Yoast\WP\SEO\post_redirect_slug_change', '__return_true');
        add_filter('wpseo_sitemap_content_before_parse_html_images', '__return_empty_string');
    }

    public function prominentWords(): void
    {
        add_filter('Yoast\WP\SEO\prominent_words_post_types', '__return_empty_array');
    }
}
