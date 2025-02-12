<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

use ACF_Post_Type;
use Sitchco\Tests\Support\TestCase;
use Sitchco\Utils\Acf;
use WP_Query;

class AcfPostTypeQueriesTest extends TestCase
{

    protected function getTestPostTitles(string $post_type): array
    {
        $query = new WP_Query();
        $results = $query->query(compact('post_type'));
        return array_column($results, 'post_title');
    }

    function test_default_query_parameters()
    {
        $acf_post_type_content = include SITCHCO_CORE_FIXTURES_DIR . '/acf-post-type.php';
        $acf_post_type = Acf::postTypeInstance();
        $post_type = $acf_post_type_content['post_type'];
        $this->factory()->post->create([
            'post_type' => $post_type, 'post_title' => '2',
            'post_date' => '2024-11-20 00:00:00',
            'meta_input' => ['active' => '0']
        ]);
        $this->factory()->post->create([
            'post_type' => $post_type, 'post_title' => '3',
            'post_date' => '2024-11-21 00:00:00',
            'meta_input' => ['active' => '1']
        ]);
        $this->factory()->post->create([
            'post_type' => $post_type, 'post_title' => '1',
            'post_date' => '2024-11-22 00:00:00',
            'meta_input' => ['active' => '1']
        ]);
        $this->assertEquals(['1', '3', '2'], $this->getTestPostTitles($post_type));

        $this->factory()->post->create([
            'post_type' => $acf_post_type->post_type,
            'post_title' => 'Performances',
            'post_content' => serialize($acf_post_type_content)
        ]);
        wp_cache_delete( acf_cache_key( $acf_post_type->cache_key_plural ), 'acf' );
        $acf_post_type->register_post_types();
        $post_type_object = get_post_type_object($post_type);
        $this->assertEquals($post_type, $post_type_object->name);
        //public
        $this->assertEquals(['3', '1'], $this->getTestPostTitles($post_type));
        //admin
        set_current_screen('edit.php?post_type=' . $post_type);
        $this->assertEquals(['1', '2', '3'], $this->getTestPostTitles($post_type));
        $this->assertDidNotDoAction('sitchco/acf_post_type_queries/admin_sort');
        $_GET['orderby'] = 'test_meta';
        $GLOBALS['wp_query']->query(compact('post_type'));
        $this->assertDidAction('sitchco/acf_post_type_queries/admin_sort');
    }
}