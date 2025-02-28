<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

class AcfPostTypeAdminSortTest extends AcfPostTypeTest
{
    public function test_admin_sortable_columns(): void
    {
        $this->createAcfPostTypeConfig();
        $screen_id = "edit-$this->post_type";
        $table = _get_list_table('WP_Posts_List_Table', ['screen' => $screen_id]);
        list(, ,$sortable) = $table->get_column_info();
        $this->assertEquals(['title', 'parent', 'comments', 'date', 'taxonomy-category', 'price_code'], array_keys($sortable));
    }

    function test_admin_main_query_sort()
    {
        global $wp_query;
        $this->createAcfPostTypeConfig();
        $this->createPosts();
        set_current_screen('edit.php?post_type=' . $this->post_type);
        $wp_query->query(['post_type' => $this->post_type]);
        $this->assertEquals(['2', '3', '1'], $this->getTestPostTitles($wp_query));
        $_GET['orderby'] = 'price_code';
        $wp_query->query(['post_type' => $this->post_type, 'orderby' => 'price_code']);
        $this->assertEquals(['1', '3', '2'], $this->getTestPostTitles($wp_query));
    }
}