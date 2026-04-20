<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

/**
 * Class YoastSEO
 * @package Sitchco\Integration
 */
class YoastSEO extends Module
{
    public const HOOK_SUFFIX = 'yoast-seo';

    const FEATURES = ['proDashboardInactive'];

    public function init(): void
    {
        add_filter('wpseo_metabox_prio', fn(): string => 'low');
        add_filter('wpseo_premium_post_redirect_slug_change', '__return_true');
        add_filter('Yoast\WP\SEO\post_redirect_slug_change', '__return_true');
        add_filter('wpseo_sitemap_content_before_parse_html_images', '__return_empty_string');
        add_filter('wpseo_indexable_excluded_post_types', [$this, 'excludeNonViewablePostTypes']);
    }

    /**
     * Exclude post types that have no public front-end view from Yoast's settings UI.
     *
     * Why: Yoast's Content Types settings only gates on `public => true`, so utility
     * post types registered with `publicly_queryable => false` (e.g. Content Partials)
     * still expose title/social/schema controls that can't affect any real output.
     */
    public function excludeNonViewablePostTypes(array $excluded): array
    {
        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            if (!is_post_type_viewable($post_type)) {
                $excluded[] = $post_type->name;
            }
        }
        return $excluded;
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
