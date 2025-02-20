<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

use ACF_Post_Type;
use Sitchco\Tests\Support\TestCase;
use Sitchco\Utils\Acf;
use WP_Query;

abstract class AcfPostTypeTest extends TestCase
{
    protected string $post_type;
    protected ACF_Post_Type $acf_post_type;
    protected array $acf_post_type_config;

    protected array $posts = [];
    protected function setUp(): void
    {
        $this->acf_post_type_config = include SITCHCO_CORE_FIXTURES_DIR . '/acf-post-type.php';
        $this->acf_post_type = Acf::postTypeInstance();
        $this->post_type = $this->acf_post_type_config['post_type'];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_post_types'][$this->post_type]);
        $this->posts = [];
        parent::tearDown();
    }

    protected function createPosts(): void
    {
        $this->posts[] = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type, 'post_title' => '2',
            'post_date' => '2024-11-20 00:00:00',
            'meta_input' => ['active' => '0', 'price_code' => 'A']
        ]);
        $this->posts[] = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type, 'post_title' => '3',
            'post_date' => '2024-11-21 00:00:00',
            'meta_input' => ['active' => '1', 'price_code' => 'B']
        ]);
        $this->posts[] = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type, 'post_title' => '1',
            'post_date' => '2024-11-22 00:00:00',
            'meta_input' => ['active' => '1', 'price_code' => 'C']
        ]);
    }

    protected function createAcfPostTypeConfig(): void
    {
        $this->factory()->post->create([
            'post_type' => $this->acf_post_type->post_type,
            'post_title' => 'Performances',
            'post_content' => serialize($this->acf_post_type_config)
        ]);
        wp_cache_delete( acf_cache_key( $this->acf_post_type->cache_key_plural ), 'acf' );
        $this->acf_post_type->register_post_types();
    }

    protected function getTestPostTitles(WP_Query $query = null): array
    {
        if (!$query) {
            $query = new WP_Query(['post_type' => $this->post_type]);
        }
        return array_column($query->posts, 'post_title');
    }
}