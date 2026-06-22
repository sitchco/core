<?php

namespace Sitchco\Tests\Model;

use Sitchco\Tests\Fakes\DataLayerPostTester;
use Sitchco\Tests\Fakes\EventPostTester;
use Sitchco\Tests\TestCase;
use Timber\Timber;

/**
 * class PostBaseTest
 *
 * Covers the PostBase data-layer template method (S2/N4): the final
 * dataLayerContext() entry point filters a protected, overridable
 * buildDataLayerContext().
 *
 * @package Sitchco\Tests\Model
 */
class PostBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('timber/post/classmap', function ($classmap) {
            $classmap['dl_tester'] = DataLayerPostTester::class;
            $classmap['event'] = EventPostTester::class;
            return $classmap;
        });
    }

    public function test_data_layer_context_strips_null_and_empty_string_values(): void
    {
        $post_id = $this->factory()->post->create(['post_type' => 'dl_tester']);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(DataLayerPostTester::class, $post);

        $context = $post->dataLayerContext();

        // null and '' are dropped, never surfaced as literal empty keys.
        $this->assertArrayNotHasKey('dropped_null', $context);
        $this->assertArrayNotHasKey('dropped_empty_string', $context);

        // Real values — and falsy-but-meaningful 0/false/[] — survive the filter.
        $this->assertSame('value', $context['kept_string']);
        $this->assertSame(0, $context['kept_zero']);
        $this->assertFalse($context['kept_false']);
        $this->assertSame([], $context['kept_empty_array']);
    }

    public function test_data_layer_context_defaults_to_empty_array(): void
    {
        $post_id = $this->factory()->post->create(['post_type' => 'event']);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(EventPostTester::class, $post);

        // A subclass with no buildDataLayerContext() override contributes nothing.
        $this->assertSame([], $post->dataLayerContext());
    }
}
