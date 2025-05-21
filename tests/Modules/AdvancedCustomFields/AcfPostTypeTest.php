<?php

namespace Sitchco\Tests\Modules\AdvancedCustomFields;

use ACF_Post_Type;
use ACF_Taxonomy;
use Sitchco\Tests\Support\TestCase;
use Sitchco\Utils\Acf;
use WP_Query;

abstract class AcfPostTypeTest extends TestCase
{
    protected string $post_type;
    protected string $taxonomy;
    protected ACF_Post_Type $acf_post_type;
    protected ACF_Taxonomy $acf_taxonomy;
    protected array $acf_post_type_config;
    protected array $acf_taxonomy_config;

    protected array $posts = [];
    protected function setUp(): void
    {
        $this->acf_post_type_config = include SITCHCO_CORE_FIXTURES_DIR . '/acf-post-type.php';
        $this->acf_post_type = Acf::postTypeInstance();
        $this->post_type = $this->acf_post_type_config['post_type'];
        $this->acf_taxonomy_config = include SITCHCO_CORE_FIXTURES_DIR . '/acf-taxonomy.php';
        $this->acf_taxonomy = Acf::taxonomyInstance();
        $this->taxonomy = $this->acf_taxonomy_config['taxonomy'];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_post_types'][$this->post_type]);
        unset($GLOBALS['wp_taxonomies'][$this->taxonomy]);
        $this->posts = [];
        parent::tearDown();
    }

    protected function createPosts(): void
    {
        $this->factory()->term->create([
            'taxonomy' => $this->taxonomy,
            'name' => 'Category 1',
            'slug' => 'category-1',
        ]);
        $this->factory()->term->create([
            'taxonomy' => $this->taxonomy,
            'name' => 'Category 2',
            'slug' => 'category-2',
        ]);
        $post1 = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type, 'post_title' => '2',
            'post_date' => '2024-11-20 00:00:00',
            'meta_input' => ['active' => '0', 'price_code' => 'A'],
        ]);
        wp_set_object_terms($post1->ID, 'category-1', $this->taxonomy);
        $this->posts[] = $post1;
        $post2 = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type, 'post_title' => '3',
            'post_date' => '2024-11-21 00:00:00',
            'meta_input' => ['active' => '1', 'price_code' => 'B'],
        ]);
        wp_set_object_terms($post2->ID, 'category-1', $this->taxonomy);
        $this->posts[] = $post2;
        $post3 = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type, 'post_title' => '1',
            'post_date' => '2024-11-22 00:00:00',
            'meta_input' => ['active' => '1', 'price_code' => 'C'],
            'tax_input' => [$this->taxonomy => 'category-2']
        ]);
        wp_set_object_terms($post3->ID, 'category-2', $this->taxonomy);
        $this->posts[] = $post2;
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
        $this->factory()->post->create([
            'post_type' => $this->acf_taxonomy->post_type,
            'post_title' => 'Performance Categories',
            'post_content' => serialize($this->acf_taxonomy_config)
        ]);
        wp_cache_delete( acf_cache_key( $this->acf_taxonomy->cache_key_plural ), 'acf' );
        $this->acf_taxonomy->register_taxonomies();
    }

    protected function getTestPostTitles(WP_Query $query = null): array
    {
        if (!$query) {
            $query = new WP_Query(['post_type' => $this->post_type]);
        }
        return array_column($query->posts, 'post_title');
    }
}
