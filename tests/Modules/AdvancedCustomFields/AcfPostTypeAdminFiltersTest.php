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
        $this->assertEquals(
            [
                'active' => [
                    'id' => 'active',
                    'options' => [
                        ['value' => '', 'label' => 'Filter by Active', 'selected' => false],
                        ['value' => '0', 'label' => '0', 'selected' => false],
                        ['value' => '1', 'label' => '1', 'selected' => false],
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
                ],
            ],
            $filters,
        );
    }

    public function test_admin_filters_with_selected_values(): void
    {
        $this->createAcfPostTypeConfig();
        $this->createPosts();
        $_GET = ['active' => '0', 'price_code' => 'B', 'performance-category' => 'category-2'];
        $filters = $this->module->renderColumnFilters($this->post_type, '');
        $this->assertEquals(
            [
                'active' => [
                    'id' => 'active',
                    'options' => [
                        ['value' => '', 'label' => 'Filter by Active', 'selected' => false],
                        ['value' => '0', 'label' => '0', 'selected' => true],
                        ['value' => '1', 'label' => '1', 'selected' => false],
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
                ],
            ],
            $filters,
        );
    }

    public function test_taxonomy_filter_includes_unpublished_and_excludes_empty_terms(): void
    {
        $this->createAcfPostTypeConfig();

        foreach (
            ['published-cat' => 'Published Cat', 'draft-cat' => 'Draft Cat', 'empty-cat' => 'Empty Cat']
            as $slug => $name
        ) {
            $this->factory()->term->create([
                'taxonomy' => $this->taxonomy,
                'name' => $name,
                'slug' => $slug,
            ]);
        }

        $published = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type,
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($published->ID, 'published-cat', $this->taxonomy);

        $draft = $this->factory()->post->create_and_get([
            'post_type' => $this->post_type,
            'post_status' => 'draft',
        ]);
        wp_set_object_terms($draft->ID, 'draft-cat', $this->taxonomy);

        // Term attached only to a different post type must not leak in
        $other = $this->factory()->post->create_and_get(['post_type' => 'post']);
        wp_set_object_terms($other->ID, 'published-cat', $this->taxonomy);

        $filters = $this->module->renderColumnFilters($this->post_type, '');
        $slugs = array_column($filters['performance-category']['options'], 'value');

        $this->assertContains('published-cat', $slugs);
        $this->assertContains('draft-cat', $slugs); // the fix: term used only by a draft
        $this->assertNotContains('empty-cat', $slugs); // still excludes truly empty terms
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
        $this->assertEquals(
            [['key' => 'active', 'value' => '0'], ['key' => 'price_code', 'value' => 'B']],
            $wp_query->query_vars['meta_query'],
        );
    }
}
