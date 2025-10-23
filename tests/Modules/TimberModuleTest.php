<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Support\DateTime;
use Sitchco\Tests\TestCase;

class TimberModuleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Load and register the ACF field group
        $field_group = include SITCHCO_CORE_FIXTURES_DIR . '/acf-field-group.php';
        acf_add_local_field_group($field_group);
    }

    public function test_acf_date_meta()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post',
        ]);
        update_field('start_time', '2026-01-01 12:30:00', $post_id);
        update_field('end_time', '2026-01-01 14:30:00', $post_id);
        $Post = \Timber\Timber::get_post($post_id);
        $this->assertInstanceOf(DateTime::class, $Post->start_time);
    }
}
