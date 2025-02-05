<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use ACF_Post_Type;
use Sitchco\Framework\Core\Module;
use WP_Query;

class CustomPostTypes extends Module
{
    public function init(): void
    {
        if (!class_exists('ACF')) {
            return;
        }

        add_filter( 'acf/post_type/additional_settings_tabs', function ( $tabs ) {
            $tabs['queries'] = 'Queries';
            $tabs['admin-ui'] = 'Admin UI';
            return $tabs;
        } );
        add_action('acf/post_type/render_settings_tab/queries', [$this, 'queriesTab']);
        add_action('acf/post_type/render_settings_tab/admin-ui', [$this, 'adminUITab']);
        add_action('init', function() {
            add_action('pre_get_posts', [$this, 'setDefaultQueryParameters']);
        });
//        add_action('admin_init', function() {
//            add_action('pre_get_posts', [$this, 'setDefaultQueryParameters']);
//        });
    }

    public function queriesTab(array $acf_post_type): void
    {
        acf_render_field_wrap(
            [
                'label'        => 'Default Query Parameters',
                'instructions' => 'Enter <code>WP_Query</code> parameters to use whenever this post type is queried, as well as the site locations where it should be applied. See the <a href="https://developer.wordpress.org/reference/classes/wp_query/#parameters" target="_blank">WP_Query docs</a> for available parameters and their usage. The "value" field accepts valid JSON for simple non-string values. For more advanced or dynamic querying, use the <code>pre_get_posts</code> filter instead.',
                'name'         => 'default_query_parameters',
                'prefix'       => 'acf_post_type',
                'value'        => $acf_post_type['default_query_parameters'] ?? [],
                'type'         => 'repeater',
                'ui'           => 1,
                'sub_fields' => [
                    [
                        'key' => 'key',
                        'label' => 'Key',
                        'name' => 'key',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'value',
                        'label' => 'Value',
                        'name' => 'value',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'location',
                        'label' => 'Location',
                        'name' => 'location',
                        'type' => 'radio',
                        'choices' => [
                            '' => 'Everywhere',
                            'public' => 'Public',
                            'admin' => 'Admin',
                        ]
                    ],
                ],
                'min' => 1,
                'max' => 10,
                'layout' => 'table',
                'button_label' => 'Add Parameter',
            ]
        );
    }

    public function adminUITab(array $acf_post_type): void
    {
        acf_render_field_wrap(
            [
                'label'        => 'Listing Screen Columns',
                'instructions' => 'Enter a custom field name to automatically display its value in the column content, or use the <code>backstage/admin_col/{name}</code> filter to display custom content.',
                'name'         => 'listing_screen_columns',
                'prefix'       => 'acf_post_type',
                'value'        => $acf_post_type['listing_screen_columns'] ?? [],
                'type'         => 'repeater',
                'ui'           => 1,
                'sub_fields' => [
                    [
                        'key' => 'name',
                        'label' => 'Name',
                        'name' => 'name',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'label',
                        'label' => 'Label',
                        'name' => 'label',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'sortable',
                        'label' => 'Sortable?',
                        'name' => 'sortable',
                        'type' => 'true_false',
                    ],
                ],
                'min' => 1,
                'max' => 10,
                'layout' => 'table',
                'button_label' => 'Add Row',
            ]
        );
    }

    protected function acfPostType(): ACF_Post_Type
    {
        $acf_post_type = acf_get_instance('ACF_Post_Type'); /* @var $acf_post_type ACF_Post_Type */
        return $acf_post_type;
    }

    protected function findPostTypeConfig(string $post_type): array|null
    {
        $acf_post_type = $this->acfPostType();
        // prevent infinite recursion
        if ($post_type == $acf_post_type->post_type) {
            return null;
        }
        $acf_post_type_posts = get_posts(['post_type' => $acf_post_type->post_type, 'posts_per_page' => -1]);
//        $acf_post_type_posts = $acf_post_type->get_posts();
        foreach ($acf_post_type_posts as $acf_post_type_post) {
            $post = $acf_post_type->get_post($acf_post_type_post->ID);
            if ($post['post_type'] === $post_type) {
                return $post;
            }
        }
        return null;
    }

    protected function setDefaultQueryParameter(array $parameter, $_, WP_Query $query): void
    {
        /**
         * Expected:
         * @var string $key
         * @var string $value
         * @var string $location
         */
        extract($parameter);
        if (
            (is_admin() && $location == 'public') ||
            (!is_admin() && $location == 'admin')
        ) {
            return;
        }
        $value = json_decode($value, true) ?? $value;
        $query->set($key, $value);
    }

    public function setDefaultQueryParameters(WP_Query $query): void
    {
        if (!($post_type_config = $this->findPostTypeConfig($query->get('post_type')))) {
            return;
        }
        if (is_admin() && isset($_GET['orderby']) && $query->is_main_query()) {
            $this->sortColumnsByMeta($query);
            return;
        }
        if ($query->get('orderby')) {
            return;
        }
        $default_query_parameters = array_values($post_type_config['default_query_parameters'] ?? []);
        array_walk($default_query_parameters, [$this, 'setDefaultQueryParameter'], $query);
    }

    protected function sortColumnsByMeta(WP_Query $query): void
    {
        $orderby = $query->get('orderby');
        if (
            // Don't sort by taxonomy
            (isset($this->register->args['taxonomies']) && in_array($orderby, $this->register->args['taxonomies'])) ||
            // Default field types offer sorting automatically
            (in_array($orderby, [
                'none',
                'ID',
                'author',
                'title',
                'name',
                'date',
                'modified',
                'parent',
                'rand',
                'comment_count',
                'menu_order',
                'meta_value',
                'meta_value_num',
                'title menu_order',
                'post__in'
            ])) ||
            !isset($this->columns[$orderby])
        ) {
            return;
        }
        $column = $this->columns[$orderby];
        $query_params = is_array($column['sortable']) ? $column['sortable'] : [
            'orderby'  => 'meta_value',
            'meta_key' => $orderby
        ];
        $query_params = apply_filters('backstage/admin_sort/' . $orderby, $query_params, $this->slug);
        foreach ($query_params as $key => $query_param) {
            $query->set($key, $query_param);
        }
    }
}