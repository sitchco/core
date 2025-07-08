<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\Stream;
use Sitchco\Tests\Support\TestCase;

/**
 * class StreamTest
 * @package Sitchco\Tests\Integration
 */
class StreamTest extends TestCase
{
    protected Stream $stream;

    public function setUp(): void
    {
        parent::setUp();
        $this->stream = new Stream();
        $this->stream->init();
    }

    public function testHooksAreRegistered(): void
    {
        global $wp_filter;

        // Verify the filter is added
        $this->assertArrayHasKey('wp_stream_settings_option_fields', $wp_filter);
        $this->assertNotEmpty($wp_filter['wp_stream_settings_option_fields']->callbacks);

        // Verify the action is added
        $this->assertArrayHasKey('admin_menu', $wp_filter);
        $this->assertNotEmpty($wp_filter['admin_menu']->callbacks);
    }

    public function testFilterDefaultMaxModifiesRecordsTTL(): void
    {
        $defaults = [
            'general' => [
                'fields' => [
                    ['name' => 'records_ttl', 'default' => 30],
                    ['name' => 'some_other_field', 'default' => 50],
                ],
            ],
        ];

        $updatedDefaults = $this->stream->filterDefaultMax($defaults);

        $this->assertEquals(90, $updatedDefaults['general']['fields'][0]['default']);
        $this->assertEquals(50, $updatedDefaults['general']['fields'][1]['default']);
    }

    public function testFilterDefaultMaxDoesNotModifyUnrelatedFields(): void
    {
        $defaults = [
            'general' => [
                'fields' => [['name' => 'other_field', 'default' => 30]],
            ],
        ];

        $updatedDefaults = $this->stream->filterDefaultMax($defaults);

        $this->assertEquals(30, $updatedDefaults['general']['fields'][0]['default']);
    }

    // TODO: fix this test!
    //    public function testAddOptionsPageAddsSubmenu(): void
    //    {
    //        global $submenu;
    //
    //        // Ensure submenu is initialized
    //        if (!is_array($submenu)) {
    //            $submenu = [];
    //        }
    //
    //        // Ensure wp_stream_get_instance exists
    //        if (! function_exists('wp_stream_get_instance')) {
    //            $this->markTestSkipped('wp_stream_get_instance is not available.');
    //        }
    //
    //        // Trigger menu registration
    //        do_action('admin_menu');
    //
    //        // Run function
    //        $this->stream->addOptionsPage();
    //
    //        // Check if the submenu item was added
    //        $this->assertArrayHasKey('wp_stream', $submenu);
    //        $this->assertTrue(
    //            in_array(
    //                ['Stream Summary', 'Summary', 'manage_options', 'wp_stream_summary'],
    //                array_map(fn($item) => array_slice($item, 0, 4), $submenu['wp_stream'])
    //            )
    //        );
    //    }

    public function testSummaryPageContentOutputsCorrectly(): void
    {
        // Ensure wp_stream_get_instance exists
        if (!function_exists('wp_stream_get_instance')) {
            $this->markTestSkipped('wp_stream_get_instance is not available.');
        }

        $_GET['start'] = '2024-02-28';

        ob_start();
        $this->stream->summaryPageContent();
        $output = ob_get_clean();
        $this->assertStringContainsString('activity-report', $output);
    }
}
