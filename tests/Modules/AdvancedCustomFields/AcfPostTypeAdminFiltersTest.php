<?php

namespace Sitchco\Tests\Modules\AdvancedCustomFields;

use Sitchco\Modules\AdvancedCustomFields\AcfPostTypeAdminFilters;

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

    public function test_admin_filters_with_selected_values(): void
    {
        $this->createAcfPostTypeConfig();
        $this->createPosts();
        $_GET = ['active' => '0', 'price_code' => 'B', 'performance-category' => 'category-2'];
        $filters = $this->module->renderColumnFilters($this->post_type, '');
        $this->assertEquals([
            'active' => [
                'id' => 'active',
                'options' => [
                    ['value' => '', 'label' => 'Filter by Active', 'selected' => false],
                    ['value' => '0', 'label' => '0', 'selected' => true],
                    ['value' => '1', 'label' => '1', 'selected' => false]
                ],
            ],
            'price_code' => [
                'id' => 'price_code',
                'options' => [
                    ['value' => '', 'label' => 'Filter by Price Code', 'selected' => false],
                    ['value' => 'A', 'label' => 'A', 'selected' => false],
                    ['value' => 'B', 'label' => 'B', 'selected' => true],
                    ['value' => 'C', 'label' => 'C', 'selected' => false],
                ],
            ],
            'performance-category' => [
                'id' => 'performance-category',
                'options' => [
                    ['value' => '', 'label' => 'All Performance Categories', 'selected' => false],
                    ['value' => 'category-1', 'label' => 'Category 1', 'selected' => false],
                    ['value' => 'category-2', 'label' => 'Category 2', 'selected' => true],
                ],
            ]
        ], $filters);
    }

    public function test_appends_parsed_query_with_selected_meta_filters(): void
    {
        global $wp_query, $pagenow;
        $this->assertHasAction('parse_query', [$this->module, 'filterColumnsByMeta']);
        $this->createAcfPostTypeConfig();
        $this->createPosts();
        set_current_screen('edit.php?post_type=' . $this->post_type);
        $pagenow = 'edit.php';
        $wp_query->query(['post_type' => $this->post_type]);
        $this->assertEmpty($wp_query->query_vars['meta_query'] ?? null);
        $_GET = ['active' => '0', 'price_code' => 'B'];
        $wp_query->query(['post_type' => $this->post_type]);
        $this->assertEquals([
            ['key' => 'active', 'value' => '0'],
            ['key' => 'price_code', 'value' => 'B'],
        ], $wp_query->query_vars['meta_query']);
    }
}
