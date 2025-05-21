<?php

namespace Sitchco\Tests\Modules\AdvancedCustomFields;

class AcfPostTypeQueriesTest extends AcfPostTypeTest
{

    function test_default_query_parameters()
    {
        $this->createAcfPostTypeConfig();
        $this->createPosts();
        $post_type_object = get_post_type_object($this->post_type);
        $this->assertEquals($this->post_type, $post_type_object->name);
        //public
        $this->assertEquals(['3', '1'], $this->getTestPostTitles());
        //admin
        set_current_screen('edit.php?post_type=' . $this->post_type);
        $this->assertEquals(['2', '3', '1'], $this->getTestPostTitles());
    }

    function test_post_menu_order()
    {
        global $wp_query;
        $this->createAcfPostTypeConfig();
        $this->createPosts();
        set_current_screen('edit.php?post_type=' . $this->post_type);
        $posts = $wp_query->query(['post_type' => $this->post_type]);
        $menu_orders = array_column($posts, 'menu_order');
        $this->assertEquals([1, 2, 3], $menu_orders);
    }
}
