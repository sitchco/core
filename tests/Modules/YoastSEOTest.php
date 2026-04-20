<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\YoastSEO;
use Sitchco\Tests\TestCase;

/**
 * class YoastSEOTest
 * @package Sitchco\Tests\Integration
 */
class YoastSEOTest extends TestCase
{
    private YoastSEO $yoastSEO;

    private array $registered_types = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->yoastSEO = $this->container->get(YoastSEO::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->registered_types as $type) {
            unregister_post_type($type);
        }
        $this->registered_types = [];
        parent::tearDown();
    }

    private function registerPostType(string $name, array $args = []): void
    {
        register_post_type($name, $args);
        $this->registered_types[] = $name;
    }

    public function testInitAddsFilters()
    {
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
        // Check that the filter is correctly added
        $this->assertNotFalse(has_filter('Yoast\WP\SEO\prominent_words_post_types'));

        // Ensure the filter returns an empty array as expected
        $this->assertSame([], apply_filters('Yoast\WP\SEO\prominent_words_post_types', ['post', 'page']));
    }

    /**
     * @dataProvider postTypeVisibilityProvider
     */
    public function testExclusionMatchesPostTypeVisibility(array $args, bool $shouldExclude)
    {
        $this->registerPostType('test_type', $args);
        $excluded = apply_filters('wpseo_indexable_excluded_post_types', []);
        $shouldExclude
            ? $this->assertContains('test_type', $excluded)
            : $this->assertNotContains('test_type', $excluded);
    }

    public static function postTypeVisibilityProvider(): array
    {
        return [
            'public but not queryable' => [['public' => true, 'publicly_queryable' => false], true],
            'public and queryable' => [['public' => true, 'publicly_queryable' => true], false],
            'private post type' => [['public' => false, 'publicly_queryable' => false], false],
        ];
    }

    public function testPreservesExistingExclusions()
    {
        $excluded = apply_filters('wpseo_indexable_excluded_post_types', ['preexisting_type']);
        $this->assertContains('preexisting_type', $excluded);
    }
}
