<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

class AcfPostTypeAdminSortTest extends AcfPostTypeTest
{
    public function test_admin_sortable_columns(): void
    {
        $table = _get_list_table('WP_Posts_List_Table');
        list(, , $sortable) = $table->get_column_info();
        $this->assertEquals(['title', 'parent', 'comments', 'date'], array_keys($sortable));
        $this->createAcfPostTypeConfig();
        $screen_id = "edit-$this->post_type";
        $table = _get_list_table('WP_Posts_List_Table', ['screen' => $screen_id]);
        list(, ,$sortable) = $table->get_column_info();
        $this->assertEquals(['title', 'parent', 'comments', 'date', 'taxonomy-category'], array_keys($sortable));
    }
}