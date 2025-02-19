<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

class AcfPostTypeAdminColumnsTest extends AcfPostTypeTest
{
    public function test_admin_columns(): void
    {
        $table = _get_list_table('WP_Posts_List_Table');
        $this->assertEquals([
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date'
        ], $table->get_columns());
        $this->createAcfPostTypeConfig();
        $screen_id = "edit-$this->post_type";
        $table = _get_list_table('WP_Posts_List_Table', ['screen' => $screen_id]);
        $this->assertEquals([
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date',
            'active' => 'Active',
            'excerpt' => 'Summary'
        ], $table->get_columns());
        ob_start();
        $table->column_default($this->posts[1], 'active');
        $content = ob_get_clean();
        $this->assertEquals('1', $content);
        ob_start();
        $table->column_default($this->posts[1], 'excerpt');
        $content = ob_get_clean();
        $this->assertEquals($this->posts[1]->post_excerpt, $content);
    }
}