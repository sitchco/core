<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Acf;
use Sitchco\Utils\Hooks;
use WP_Query;

/**
 * Class AcfPostTypeAdminColumns
 * @package Sitchco\Integration\AdvancedCustomFields
 *
 * Adds configurations settings and display behavior for admin post type columns
 */
class AcfPostTypeAdminColumns extends Module
{
    protected AcfSettings $settings;

    const HOOK_NAME = 'acf_post_type_admin_columns';

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

        $this->settings->addSettingsTab('admin-columns', 'Admin Columns', [$this, 'adminColumnsTab']);
        add_action('acf/init', fn() => add_action('registered_post_type', [$this, 'postTypeConfigHooks']), 5);
        add_filter(static::hookName('column_content'), [$this, 'postMeta'], 5, 3);
        add_filter(static::hookName('column_content', 'editor'), [$this, 'editor'], 5, 2);
        add_filter(static::hookName('column_content', 'excerpt'), [$this, 'excerpt'], 5, 2);
        // Fixes ACF true_false field layout bug when in a table row
        add_action('admin_print_styles', function() {
            $screen = get_current_screen();
            if ($screen->id !== Acf::postTypeInstance()->post_type) {
                return;
            }
            echo "<style>.acf-admin-page .acf-row .acf-field-true-false { display: table-cell; }</style>";
        });
    }

    /**
     * Adds Listing Screen Columns settings to ACF post type configuration screen
     *
     * @param array $values
     * @return void
     */
    public function adminColumnsTab(array $values): void
    {
        $this->settings->addSettingsField('listing_screen_columns', [
            'label'        => 'Listing Screen Columns',
            'instructions' => 'Enter a custom field name to automatically display its value in the column content, or use the <code>' . static::hookName('column_content', '{{column_name}}'). '</code> filter to display custom content.',
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
            ],
            'min' => 1,
            'max' => 10,
            'layout' => 'table',
            'button_label' => 'Add Row',
        ], $values);
    }

    /**
     * Hooks for the configured post type's listing screen
     *
     * @param string $post_type
     * @return void
     */
    public function postTypeConfigHooks(string $post_type): void
    {
        $post_type_config = Acf::findPostTypeConfig($post_type);
        if (!$post_type_config) {
            return;
        }
        add_filter("manage_{$post_type}_posts_columns", fn(array $columns) => $this->columnHeaders($columns, $post_type_config));
        add_action("manage_{$post_type}_posts_custom_column", fn(string $column_name, int $post_id) => $this->columnContent($column_name, $post_id, $post_type_config), 10, 2);
        add_action('admin_print_styles', fn() => $this->outputAdminStyles($post_type));
        do_action(static::hookName('post_type_config'), $post_type, $post_type_config);
    }

    /**
     * Extracts and normalizes the listing screen columns from the entire config
     *
     * @param array $post_type_config
     * @return array
     */

    public static function getColumnConfig(array $post_type_config): array
    {
        $columns = array_filter((array) ($post_type_config['listing_screen_columns'] ?? []));
        return array_filter(array_values($columns), fn($row) => !!$row['name'] && !!$row['label']);
    }

    /**
     * Customize admin columns, but ensure cb, title, and date remain
     * in the column list if not otherwise specified.
     */
    protected function columnHeaders(array $columns, array $post_type_config): array
    {
        $headers = [];

        foreach ($this->getColumnConfig($post_type_config) as $row) {
            $key = $row['name'];
            $tax_key = 'taxonomy-' . $key;
            $header_key = array_key_exists($tax_key, $columns) ? $tax_key : $key;
            $headers[$header_key] = $row['label'] ;
        }
        $taxonomies = array_filter(array_keys($columns), fn($key) => str_starts_with($key, 'taxonomy-'));

        $get_defaults = fn($arr) => array_intersect_key($columns, array_diff_key(array_flip($arr), $headers));

        $columns = empty($headers) ?
            $columns :
            array_merge($get_defaults([
                'cb',
                'title'
            ]), $headers, $get_defaults($taxonomies), $get_defaults(['author', 'comments', 'date']));
        /* Hook: sitchco/acf_post_type_admin_columns/column_headers */
        return apply_filters(static::hookName('column_headers'), $columns, $post_type_config);
    }

    /**
     * Displays content for a column
     *
     * @param string $column_name
     * @param int $post_id
     * @param array $post_type_config
     * @return false|void
     */

    protected function columnContent(string $column_name, int $post_id, array $post_type_config) {
        $column_config = $this->getColumnConfig($post_type_config);
        // Don't display anything if this column isn't configured
        if (!in_array($column_name, array_column($column_config, 'name'))) {
            return;
        }
        $slug = $post_type_config['post_type'];
        $filter_base = 'column_content';
        $content = '';
        /**
         * add_filter('sitchco/acf_post_type_admin_columns/column_content', 'my_func', 10, 4);
         * function my_func($content, $post_id, $column_id, $post_type_config){ return $content; }
         */
        $content = apply_filters(static::hookName($filter_base), $content, $post_id, $column_name, $post_type_config);
        /**
         * add_filter('sitchco/acf_post_type_admin_columns/column_content/{{column_name}}', 'my_func', 10, 3);
         * function my_func($content, $post_id, $post_type_config){ return $content; }
         */
        $content = apply_filters(static::hookName($filter_base, $column_name), $content, $post_id, $post_type_config);
        /**
         * add_filter('sitchco/acf_post_type_admin_columns/column_content/{{column_name}}/{{post_type}}', 'my_func', 10, 2);
         * function my_func($content, $post_id, $post_type_config){ return $content; }
         */
        $content = apply_filters(static::hookName($filter_base, $column_name, $slug), $content, $post_id, $post_type_config);

        if (is_array($content)) {
            $content = implode(' | ', $content);
        }
        echo $content;
    }

    /**
     * Adds styles to correctly display a thumbnail image in the listing table
     *
     * @param string $post_type
     * @return void
     */

    protected function outputAdminStyles(string $post_type): void
    {
        /* Hook: sitchco/acf_post_type_admin_columns/output_styles */
        if (!apply_filters(static::hookName('output_styles'), true)) {
            return;
        }
        $screen = get_current_screen();
        if ($screen->id !== 'edit-' . $post_type) {
            return;
        }
        $styles = apply_filters(static::hookName('output_styles', $post_type), '.column-thumbnail { text-align: center; width:75px; } .column-thumbnail img{ display:block;margin: 0 auto;max-width:100%; height:auto; }');
        echo "<style>$styles</style>";
    }

    public function postMeta($content, $post_id, $column_id) {
        return get_post_meta($post_id, $column_id) ?: $content;
    }

    public function editor($content, $post_id): string
    {
        $post = get_post($post_id);

        return strip_tags($post->post_content) ?: $content;
    }

    public function excerpt($content, $post_id): string
    {
        $post = get_post($post_id);

        return strip_tags($post->post_excerpt) ?: $content;
    }
}