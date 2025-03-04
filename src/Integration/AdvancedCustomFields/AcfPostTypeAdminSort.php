<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;
use Sitchco\Utils\Hooks;
use WP_Query;

/**
 * Class AcfPostTypeAdminSort
 * @package Sitchco\Integration\AdvancedCustomFields
 *
 * Adds sortable flag to admin columns configuration and modifies admin sort query
 */
class AcfPostTypeAdminSort extends Module
{
    protected AcfSettings $settings;

    const DEPENDENCIES = [
        AcfPostTypeAdminColumns::class,
    ];

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

        $this->settings->extendSettingsField('listing_screen_columns', function($field) {
            $field['sub_fields'][] = [
                'key' => 'sortable',
                'label' => 'Sortable?',
                'name' => 'sortable',
                'type' => 'true_false',
            ];
            return $field;
        });
        add_action(AcfPostTypeAdminColumns::hookName('post_type_config'), [$this, 'postTypeConfig'], 10, 2);
        add_action(AcfPostTypeQueries::hookName('admin_sort'), [$this, 'adminMainQuerySort'], 10, 3);
    }

    /**
     * Adds WP sortable columns hook
     *
     * @param string $post_type
     * @param array $post_type_config
     * @return void
     */

    public function postTypeConfig(string $post_type, array $post_type_config): void
    {
        add_filter("manage_edit-{$post_type}_sortable_columns", fn(array $columns) => $this->sortableColumns($columns, $post_type_config));
    }

    /**
     * Sets meta orderby from sortable columns configuration
     *
     * @param string $orderby
     * @param array $post_type_config
     * @param WP_Query $query
     * @return void
     */
    public function adminMainQuerySort(string $orderby, array $post_type_config, WP_Query $query): void
    {
        $sortable_columns = static::getSortableColumns($post_type_config);
        if (!in_array($orderby,  array_column($sortable_columns, 'name'))) {
            return;
        }
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value');
    }

    /**
     * Appends list of sortable columns
     *
     * @param array $columns
     * @param array $post_type_config
     * @return array
     */
    protected function sortableColumns(array $columns, array $post_type_config): array
    {
        foreach ((array) $post_type_config['taxonomies'] as $taxonomy) {
            $columns['taxonomy-' . $taxonomy] = 'taxonomy-' . $taxonomy;
        }

        foreach (static::getSortableColumns($post_type_config) as $row) {
            $columns[$row['name']] = $row['name'];
        }

        return $columns;
    }

    /**
     * Extracts and normalizes sortable column configuration
     *
     * @param array $post_type_config
     * @return array
     */
    protected static function getSortableColumns(array $post_type_config): array
    {
        return array_filter(AcfPostTypeAdminColumns::getColumnConfig($post_type_config), fn($row) => $row['sortable'] ?? false);
    }
}