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

    public function test_custom_filter_labels_use_column_content(): void
    {
        $this->createAcfPostTypeConfig();
        $this->createPosts();

        // One handler now drives both the column cell and the filter labels
        add_filter('sitchco/acf_post_type_admin_columns/column_content/price_code', function ($content) {
            $code = is_array($content) ? $content[0] ?? '' : $content;
            return $code === '' ? '' : "Tier {$code}";
        });

        $_GET = ['price_code' => 'B'];
        $filters = $this->module->renderColumnFilters($this->post_type, '');

        $this->assertEquals(
            [
                ['value' => '', 'label' => 'Filter by Price Code', 'selected' => false],
                ['value' => 'A', 'label' => 'Tier A', 'selected' => false],
                ['value' => 'B', 'label' => 'Tier B', 'selected' => true],
                ['value' => 'C', 'label' => 'Tier C', 'selected' => false],
            ],
            $filters['price_code']['options'],
        );
    }

    public function test_taxonomy_filter_includes_unpublished_and_excludes_empty_terms(): void
    {
        $this->createAcfPostTypeConfig();

        foreach (
            [
                'published-cat' => 'Published Cat',
                'draft-cat' => 'Draft Cat',
                'pending-cat' => 'Pending Cat',
                'future-cat' => 'Future Cat',
                'private-cat' => 'Private Cat',
                'empty-cat' => 'Empty Cat',
                'foreign-cat' => 'Foreign Cat',
            ]
            as $slug => $name
        ) {
            $this->factory()->term->create([
                'taxonomy' => $this->taxonomy,
                'name' => $name,
                'slug' => $slug,
            ]);
        }

        // One CPT post per listing-visible status, each attached to its own term
        $statuses = [
            'publish' => 'published-cat',
            'draft' => 'draft-cat',
            'pending' => 'pending-cat',
            'future' => 'future-cat',
            'private' => 'private-cat',
        ];
        foreach ($statuses as $status => $term_slug) {
            $post = $this->factory()->post->create_and_get([
                'post_type' => $this->post_type,
                'post_status' => $status,
                'post_date' => $status === 'future' ? '2999-01-01 00:00:00' : '2024-11-20 00:00:00',
            ]);
            wp_set_object_terms($post->ID, $term_slug, $this->taxonomy);
        }

        // Term attached only to a different post type must not leak in
        $other = $this->factory()->post->create_and_get(['post_type' => 'post']);
        wp_set_object_terms($other->ID, 'foreign-cat', $this->taxonomy);

        $filters = $this->module->renderColumnFilters($this->post_type, '');
        $slugs = array_column($filters['performance-category']['options'], 'value');

        // The fix: terms surface for every listing-visible status, not just published
        foreach ($statuses as $term_slug) {
            $this->assertContains($term_slug, $slugs);
        }
        $this->assertNotContains('empty-cat', $slugs); // still excludes truly empty terms
        $this->assertNotContains('foreign-cat', $slugs); // excludes terms used only by other post types
    }

    public function test_filterable_editor_excerpt_column_does_not_fatal(): void
    {
        // Mark the built-in excerpt column filterable; renderColumnContent() will route its values
        // through the excerpt() handler with post_id 0, which previously fataled on get_post(0).
        $this->acf_post_type_config['listing_screen_columns']['row-row-1']['filterable'] = '1';
        $this->createAcfPostTypeConfig();

        $this->factory()->post->create([
            'post_type' => $this->post_type,
            'meta_input' => ['excerpt' => 'Some Summary'],
        ]);
        $this->factory()->post->create([
            'post_type' => $this->post_type,
            'meta_input' => ['excerpt' => 'Another Summary'],
        ]);

        $filters = $this->module->renderColumnFilters($this->post_type, '');

        $this->assertArrayHasKey('excerpt', $filters);
        foreach ($filters['excerpt']['options'] as $option) {
            $this->assertIsString($option['label']);
        }
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
