<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

class AcfPostTypeAdminFiltersTest extends AcfPostTypeTest
{
    public function test_admin_filters(): void
    {

        $filters = apply_filters('restrict_manage_posts', $this->post_type, '');
        $this->assertEquals([], $filters);
        $this->createAcfPostTypeConfig();
        $filters = apply_filters('restrict_manage_posts', $this->post_type, '');
        $this->assertEquals([], $filters);
        $this->createPosts();
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
}