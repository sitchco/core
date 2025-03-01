<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;
use WP_Post, WP_Query;

class AcfPostTypeQueries extends Module
{
    protected AcfSettings $settings;

    const HOOK_NAME = 'acf_post_type_queries';

    public function __construct(AcfSettings $settings)
    {
        $this->settings = $settings;
    }

    public function init(): void
    {
        if (!class_exists('ACF')) {
            return;
        }
        $this->settings->addSettingsTab('queries', 'Queries', [$this, 'queriesTab']);
        add_action('init', function() {
            add_action('pre_get_posts', [$this, 'setDefaultQueryParameters']);
        });
        add_action('save_post', [$this, 'savePost'], 13, 2);
    }

    public function queriesTab(array $values): void
    {
        $this->settings->addSettingsField('default_query_parameters', [
            'label'        => 'Default Query Parameters',
            'instructions' => 'Enter <code>WP_Query</code> parameters to use whenever this post type is queried, as well as the site locations where it should be applied. See the <a href="https://developer.wordpress.org/reference/classes/wp_query/#parameters" target="_blank">WP_Query docs</a> for available parameters and their usage. The "value" field accepts valid JSON for simple non-string values. For more advanced or dynamic querying, use the <code>pre_get_posts</code> filter instead.',
            'type'         => 'repeater',
            'ui'           => 1,
            'sub_fields' => [
                [
                    'key' => 'key',
                    'label' => 'Key',
                    'name' => 'key',
                    'type' => 'text',
                    'instructions' => '',
                ],
                [
                    'key' => 'value',
                    'label' => 'Value',
                    'name' => 'value',
                    'type' => 'text',
                    'instructions' => '',
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
                    ],
                    'instructions' => '',
                ],
            ],
            'min' => 1,
            'max' => 10,
            'layout' => 'table',
            'button_label' => 'Add Parameter',
        ], $values);
    }

    public static function getDefaultQueryParameters(array $post_type_config): array
    {
        $parameters = array_filter((array) ($post_type_config['default_query_parameters'] ?? []));
        return array_filter(array_values($parameters), fn($row) => !!$row['key'] && !!$row['value']);
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
        $default_query_parameters = static::getDefaultQueryParameters($post_type_config);
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
        do_action(static::hookName('admin_sort'), $orderby, $post_type_config, $query);
    }

    private function isMenuOrder(array $post_type_config): bool
    {
        $default_query_parameters = static::getDefaultQueryParameters($post_type_config);
        return !!count(array_filter($default_query_parameters, fn($row) => $row['key'] === 'orderby' && $row['value'] === 'menu_order'));
    }

    /**
     * When sorting posts by menu_order, we will increment the order on new cpt pages.
     * Default menu_order is 0 - seems to be more intuitive to add new pages
     * at the bottom of the list instead.
     *
     * @param int $post_id
     * @param WP_Post $post_obj
     */
    public function savePost(int $post_id, WP_Post $post_obj): void
    {
        if (!apply_filters(static::hookName('increment_post_menu_order'), true, $post_obj)) {
            return;
        }
        if (!($post_type_config = Acf::findPostTypeConfig($post_obj->post_type))) {
            return;
        }
        remove_action('save_post', [$this, 'savePost'], 13);
        if ($this->isMenuOrder($post_type_config)) {
            $this->setPostMenuOrder($post_obj);
        }
        add_action('save_post', [$this, 'savePost'], 13, 2);
    }

    public function setPostMenuOrder(WP_Post $post_obj): void
    {
        if (
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            in_array($post_obj->post_status, ['auto-draft', 'inherit']) ||
            0 != $post_obj->menu_order
        ) {
            return;
        }
        global $wpdb;
        $result = $wpdb->get_results($wpdb->prepare(
            "SELECT MAX(menu_order) AS menu_order FROM $wpdb->posts WHERE post_type=%s", $post_obj->post_type
        ), ARRAY_A);
        $order = intval($result[0]['menu_order']) + 1;
        $post_obj->menu_order = $order;
        wp_update_post($post_obj);
    }
}