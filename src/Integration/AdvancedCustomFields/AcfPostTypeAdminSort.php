<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;
use Sitchco\Utils\Hooks;
use WP_Query;

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

    public function init(): void
    {
        if (!class_exists('ACF')) {
            return;
        }

        $this->settings->extendSettingsField('listing_screen_columns', function($field) {
            $field['sub_fields'] = array_merge(
                $field['sub_fields'],
                [
                    [
                        'key' => 'sortable',
                        'label' => 'Sortable?',
                        'name' => 'sortable',
                        'type' => 'true_false',
                    ]
                ]
            );
            return $field;
        });
        add_action(AcfPostTypeAdminColumns::hookName('post_type_config'), [$this, 'postTypeConfig'], 10, 2);
        add_action(AcfPostTypeQueries::hookName('admin_sort'), [$this, 'adminMainQuerySort'], 10, 3);
    }

    public function postTypeConfig(string $post_type, array $post_type_config): void
    {
        add_filter("manage_edit-{$post_type}_sortable_columns", fn(array $columns) => $this->sortableColumns($columns, $post_type_config));
    }

    public function adminMainQuerySort(string $orderby, array $post_type_config, WP_Query $query): void
    {
        $sortable_columns = static::getSortableColumns($post_type_config);
        if (!in_array($orderby,  array_column($sortable_columns, 'name'))) {
            return;
        }
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value');
    }

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

    protected static function getSortableColumns(array $post_type_config): array
    {
        return array_filter(AcfPostTypeAdminColumns::getColumnConfig($post_type_config), fn($row) => $row['sortable']);
    }
}