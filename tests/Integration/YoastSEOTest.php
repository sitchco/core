<?php

namespace Sitchco\Tests\Integration;

use Sitchco\Tests\Support\TestCase;
use Sitchco\Integration\Yoast\YoastSEO;

/**
 * class YoastSEOTest
 * @package Sitchco\Tests\Integration
 */
class YoastSEOTest extends TestCase
{
    private YoastSEO $yoastSEO;

    public function setUp(): void
    {
        parent::setUp();
        $this->yoastSEO = new YoastSEO();
        $this->yoastSEO->init();
    }

    public function testInitAddsFilters()
    {
        do_action('muplugins_loaded');

        // Check that filters are correctly added
        $this->assertNotFalse(has_filter('wpseo_metabox_prio'));
        $this->assertNotFalse(has_filter('wpseo_premium_post_redirect_slug_change'));
        $this->assertNotFalse(has_filter('Yoast\WP\SEO\post_redirect_slug_change'));
        $this->assertNotFalse(has_filter('wpseo_sitemap_content_before_parse_html_images'));

        // Check if the correct callbacks are registered
        $this->assertSame('low', apply_filters('wpseo_metabox_prio', 'default'));
        $this->assertTrue(apply_filters('wpseo_premium_post_redirect_slug_change', false));
        $this->assertTrue(apply_filters('Yoast\WP\SEO\post_redirect_slug_change', false));
        $this->assertSame('', apply_filters('wpseo_sitemap_content_before_parse_html_images', 'some value'));
    }

    public function testProminentWordsAddsFilter()
    {
        $this->yoastSEO->proDashboardInactive();

        // Check that the filter is correctly added
        $this->assertNotFalse(has_filter('Yoast\WP\SEO\prominent_words_post_types'));

        // Ensure the filter returns an empty array as expected
        $this->assertSame([], apply_filters('Yoast\WP\SEO\prominent_words_post_types', ['post', 'page']));
    }
}
