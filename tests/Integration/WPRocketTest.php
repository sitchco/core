<?php

namespace Sitchco\Tests\Integration;

use Sitchco\Tests\Support\TestCase;
use Sitchco\Integration\WPRocket\WPRocket;

/**
 * class WPRocketTest
 * @package Sitchco\Tests\Integration
 */
class WPRocketTest extends TestCase
{
    private WPRocket $wprocket;

    public function setUp(): void
    {
        parent::setUp();
        $this->wprocket = new WPRocket();
        $this->wprocket->init();
    }

    public function testInitAddsFilters()
    {
        do_action('muplugins_loaded');

        // Check that filters are correctly added
        $this->assertNotFalse(has_filter('before_rocket_htaccess_rules', [$this->wprocket, 'forceTrailingSlash']));
        $this->assertNotFalse(has_filter('rocket_metabox_options_post_types', '__return_empty_array'));
        $this->assertNotFalse(has_filter('rocket_preload_cache_pending_jobs_cron_rows_count'));
        $this->assertNotFalse(has_filter('rocket_preload_pending_jobs_cron_interval'));
        $this->assertNotFalse(has_filter('rocket_preload_delay_between_requests'));
        $this->assertNotFalse(has_filter('rocket_rucss_pending_jobs_cron_rows_count'));
        $this->assertNotFalse(has_filter('rocket_rucss_pending_jobs_cron_interval'));

        $marker = 'test_marker_content';
        $filtered_output = apply_filters('before_rocket_htaccess_rules', $marker);
        $this->assertStringContainsString('# Force trailing slash', $filtered_output);
        $this->assertStringContainsString('RewriteEngine On', $filtered_output);
        $this->assertStringContainsString("RewriteRule ^(.*)$ http://%{HTTP_HOST}/$1/ [L,R=301]", $filtered_output);
        $this->assertStringContainsString($marker, $filtered_output);

        $this->assertSame([], apply_filters('rocket_metabox_options_post_types', ['post', 'page']));
        $this->assertSame(25, apply_filters('rocket_preload_cache_pending_jobs_cron_rows_count', 10));
        $this->assertSame(120, apply_filters('rocket_preload_pending_jobs_cron_interval', 60));
        $this->assertSame(2000000, apply_filters('rocket_preload_delay_between_requests', 1000000));
        $this->assertSame(25, apply_filters('rocket_rucss_pending_jobs_cron_rows_count', 10));
        $this->assertSame(300, apply_filters('rocket_rucss_pending_jobs_cron_interval', 100));
    }

    /**
     * Test that forceTrailingSlash generates the correct htaccess rules.
     */
    public function testForceTrailingSlashGeneratesCorrectHtaccess()
    {
        // Create an instance of WPRocket and call forceTrailingSlash().
        $marker = 'some_marker_content';
        $output = $this->wprocket->forceTrailingSlash($marker);

        // Check if the output contains expected content.
        $this->assertStringContainsString('# Force trailing slash', $output);
        $this->assertStringContainsString('RewriteEngine On', $output);
        $this->assertStringContainsString("RewriteRule ^(.*)$ http://%{HTTP_HOST}/$1/ [L,R=301]", $output); // Assuming non-SSL environment.
        $this->assertStringContainsString($marker, $output);
    }
}
