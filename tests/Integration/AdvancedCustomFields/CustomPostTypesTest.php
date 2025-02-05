<?php

namespace Sitchco\Tests\AdvancedCustomFields;

use Sitchco\Tests\Support\TestCase;
use WP_Query;


class CustomPostTypesTest extends TestCase
{

    protected function getTestPostTitles(string $post_type): array
    {
//        wp_cache_flush('post-queries');
        $query = new WP_Query();
        $results = $query->query(compact('post_type'));
        return array_column($results, 'post_title');
    }

    function test_default_query_parameters()
    {
        $acf_post_type_content = include SITCHCO_CORE_FIXTURES_DIR . '/acf-post-type.php';
        $acf_post_type = acf_get_instance('ACF_Post_Type');
        //$acf_post_type_posts = get_posts(['post_type' => $acf_post_type->post_type, 'posts_per_page' => -1]);
//        foreach ($acf_post_type_posts as $acf_post_type_post) {
//            wp_delete_post($acf_post_type_post->ID, true);
//        }
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
        $post_type_object = get_post_type_object($post_type);
        $this->assertEquals($post_type, $post_type_object->name);
        //public
        $this->assertEquals(['3', '1'], $this->getTestPostTitles($post_type));
        //admin
        set_current_screen('edit.php?post_type=' . $post_type);
        $this->assertEquals(['1', '2', '3'], $this->getTestPostTitles($post_type));
    }
}