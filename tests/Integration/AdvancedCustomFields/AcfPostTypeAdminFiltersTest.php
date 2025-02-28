<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

class AcfPostTypeAdminFiltersTest extends AcfPostTypeTest
{
    public function test_admin_filter(): void
    {
        $this->createPosts();
        $filters = apply_filters('restrict_manage_posts', $this->post_type, '');
        $this->assertEquals([], $filters);
        $this->createAcfPostTypeConfig();
        $filters = apply_filters('restrict_manage_posts', $this->post_type, '');
        $this->assertEquals([
            'active' => [
                'id' => 'active',
                'options' => [
                    ['value' => '', 'label' => 'Filter by Active', 'selected' => false],
                    ['value' => '0', 'label' => '0', 'selected' => false],
                    ['value' => '1', 'label' => '1', 'selected' => false]
                ],
            ],
            'price_code' => [
                'id' => 'price_code',
                'options' => [
                    ['value' => '', 'label' => 'Filter by Price Code', 'selected' => false],
                    ['value' => 'A', 'label' => 'A', 'selected' => false],
                    ['value' => 'B', 'label' => 'B', 'selected' => false],
                    ['value' => 'C', 'label' => 'C', 'selected' => false],
                ],
            ]
        ], $filters);
    }

    function test_admin_main_query_sort()
    {
        global $wp_query;
        $this->createPosts();
        $this->createAcfPostTypeConfig();
        set_current_screen('edit.php?post_type=' . $this->post_type);
        $wp_query->query(['post_type' => $this->post_type]);
        $this->assertEquals(['2', '3', '1'], $this->getTestPostTitles($wp_query));
        $_GET['orderby'] = 'price_code';
        $wp_query->query(['post_type' => $this->post_type, 'orderby' => 'price_code']);
        $this->assertEquals(['1', '3', '2'], $this->getTestPostTitles($wp_query));

    }
}