<?php

namespace Sitchco\Tests\Integration\AdvancedCustomFields;

use Sitchco\Integration\AdvancedCustomFields\AcfPostTypeAdminFilters;

class AcfPostTypeAdminFiltersTest extends AcfPostTypeTest
{
    private AcfPostTypeAdminFilters $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(AcfPostTypeAdminFilters::class);
    }

    public function test_admin_filters(): void
    {
        $this->assertHasAction('restrict_manage_posts', [$this->module, 'renderColumnFilters']);
        $filters = $this->module->renderColumnFilters($this->post_type, '');
        $this->assertEquals([], $filters);
        $this->createAcfPostTypeConfig();
        $filters = $this->module->renderColumnFilters($this->post_type, '');
        $this->assertEquals([], $filters);
        $this->createPosts();
        $filters = $this->module->renderColumnFilters($this->post_type, '');
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
            ],
            'performance-category' => [
                'id' => 'performance-category',
                'options' => [
                    ['value' => '', 'label' => 'All Performance Categories', 'selected' => false],
                    ['value' => 'category-1', 'label' => 'Category 1', 'selected' => false],
                    ['value' => 'category-2', 'label' => 'Category 2', 'selected' => false],
                ],
            ]
        ], $filters);
    }
}