<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

use WP_Query;

class AcfPostTypeQueriesTest extends AcfPostTypeTest
{

    protected function getTestPostTitles(): array
    {
        $query = new WP_Query();
        $results = $query->query(['post_type' => $this->post_type]);
        return array_column($results, 'post_title');
    }

    function test_default_query_parameters()
    {
        $this->assertEquals(['1', '3', '2'], $this->getTestPostTitles());

        $this->createAcfPostTypeConfig();
        $post_type_object = get_post_type_object($this->post_type);
        $this->assertEquals($this->post_type, $post_type_object->name);
        //public
        $this->assertEquals(['3', '1'], $this->getTestPostTitles());
        //admin
        set_current_screen('edit.php?post_type=' . $this->post_type);
        $this->assertEquals(['1', '2', '3'], $this->getTestPostTitles());
        $this->assertDidNotDoAction('sitchco/acf_post_type_queries/admin_sort');
        $_GET['orderby'] = 'test_meta';
        $GLOBALS['wp_query']->query(['post_type' => $this->post_type]);
        $this->assertDidAction('sitchco/acf_post_type_queries/admin_sort');
    }
}