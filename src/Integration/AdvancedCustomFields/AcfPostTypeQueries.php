<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;
use WP_Post, WP_Query;

/**
 * Class AcfPostTypeQueries
 * @package Sitchco\Integration\AdvancedCustomFields
 *
 * Adds configuration settings to set default query parameters for a post type
 */
class AcfPostTypeQueries extends Module
{
    protected AcfSettings $settings;

    const HOOK_NAME = 'acf_post_type_queries';

    public function __construct(AcfSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initialization hooks and configuration
     *
     * @return void
     */
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

    /**
     * Add Default Query Parameters settings to ACF post type configuration screen
     *
     * @param array $values
     * @return void
     */

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

    /**
     * Extracts and normalizes the default query parameters from the entire config
     *
     * @param array $post_type_config
     * @return array
     */
    public static function getDefaultQueryParameters(array $post_type_config): array
    {
        $parameters = array_filter((array) ($post_type_config['default_query_parameters'] ?? []));
        return array_filter(array_values($parameters), fn($row) => !!$row['key'] && !!$row['value']);
    }

    /**
     * Sets a single query parameters
     *
     * @param array $parameter
     * @param int $index
     * @param WP_Query $query
     * @return void
     */

    protected function setDefaultQueryParameter(array $parameter, int $index, WP_Query $query): void
    {
        /**
         * Expected:
         * @var string $key
         * @var string $value
         * @var string $location
         */
        extract($parameter);
        // Location doesn't match configured location
        if (
            (is_admin() && $location == 'public') ||
            (!is_admin() && $location == 'admin')
        ) {
            return;
        }
        $value = json_decode($value, true) ?? $value;
        $query->set($key, $value);
    }

    /**
     * Set all default query parameters for the requested post type
     *
     * @param WP_Query $query
     * @return void
     */
    public function setDefaultQueryParameters(WP_Query $query): void
    {
        $post_type = $query->get('post_type');
        // Ignore when multiple post types involved, i.e. search
        if (is_array($post_type)) {
            return;
        }
        $post_type_config = Acf::findPostTypeConfig($post_type);
        // No configuration for this post type
        if (!$post_type_config) {
            return;
        }
        // Allow admin orderby query to take precedence
        if (is_admin() && isset($_GET['orderby']) && $query->is_main_query()) {
            $this->adminSortHook($query, $post_type_config);
            return;
        }
        // Allow an existing set orderby to take precedence
        if ($query->get('orderby')) {
            return;
        }
        $default_query_parameters = static::getDefaultQueryParameters($post_type_config);
        array_walk($default_query_parameters, [$this, 'setDefaultQueryParameter'], $query);
    }

    /**
     * Allow hooking into a non-taxonomy admin orderby
     *
     * @param WP_Query $query
     * @param array $post_type_config
     * @return void
     */

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

    /**
     * Is one of the default parameters configured to orderby menu_order
     *
     * @param array $post_type_config
     * @return bool
     */
    protected function isMenuOrder(array $post_type_config): bool
    {
        $default_query_parameters = static::getDefaultQueryParameters($post_type_config);
        return !!count(array_filter($default_query_parameters, fn($row) => $row['key'] === 'orderby' && $row['value'] === 'menu_order'));
    }

    /**
     * Increments menu order on a post
     *
     * @param WP_Post $post_obj
     * @return void
     */
    protected function setPostMenuOrder(WP_Post $post_obj): void
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
        // Allow explicit disabling via filter sitchco/acf_post_type_queries/increment_post_menu_order
        if (!apply_filters(static::hookName('increment_post_menu_order'), true, $post_obj)) {
            return;
        }
        // No configuration for this post type
        if (!($post_type_config = Acf::findPostTypeConfig($post_obj->post_type))) {
            return;
        }
        remove_action('save_post', [$this, 'savePost'], 13);
        if ($this->isMenuOrder($post_type_config)) {
            $this->setPostMenuOrder($post_obj);
        }
        add_action('save_post', [$this, 'savePost'], 13, 2);
    }


}