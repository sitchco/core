<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use ACF_Post_Type;
use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;
use WP_Query;

class AcfPostTypeQueries extends Module
{
    const HOOK_NAME = 'acf_post_type_queries';

    public function init(): void
    {
        if (!class_exists('ACF')) {
            return;
        }

        add_filter( 'acf/post_type/additional_settings_tabs', function ($tabs) {
            $tabs['queries'] = 'Queries';
            return $tabs;
        } );
        add_action('acf/post_type/render_settings_tab/queries', [$this, 'queriesTab']);
        add_action('init', function() {
            add_action('pre_get_posts', [$this, 'setDefaultQueryParameters']);
        });
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
        if (!($post_type_config = Acf::findPostTypeConfig($query->get('post_type')))) {
            return;
        }
        if (is_admin() && isset($_GET['orderby']) && $query->is_main_query()) {
            $this->adminSortHook($query, $post_type_config);
            return;
        }
        if ($query->get('orderby')) {
            return;
        }
        $default_query_parameters = array_values($post_type_config['default_query_parameters'] ?? []);
        array_walk($default_query_parameters, [$this, 'setDefaultQueryParameter'], $query);
    }

    protected function adminSortHook(WP_Query $query, array $post_type_config): void
    {
        $orderby = $query->get('orderby');
        if (
            // Don't sort by taxonomy
            ($post_type_config['taxonomies'] && in_array($orderby, (array) $post_type_config['taxonomies'])) ||
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
            ]))
        ) {
            return;
        }
        do_action(static::hookName('admin_sort'), $orderby, $post_type_config['post_type'], $query);
    }
}