<?php

namespace Sitchco\Modules\AdvancedCustomFields;

use Sitchco\Framework\Module;
use Sitchco\Support\AcfSettings;
use Sitchco\Utils\Acf;
use Sitchco\Utils\Template;
use WP_Query;
use wpdb;

/**
 * Class AcfPostTypeAdminSort
 * @package Sitchco\Integration\AdvancedCustomFields
 *
 * Adds filterable flag to admin columns configuration, displays filter UI controls
 * and modifies query based on filtered value request
 */
class AcfPostTypeAdminFilters extends Module
{
    protected AcfSettings $settings;

    const HOOK_SUFFIX = 'acf_post_type_admin_filters';

    const DEPENDENCIES = [AcfPostTypeAdminColumns::class];

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
        add_action('restrict_manage_posts', [$this, 'renderColumnFilters'], 20, 2);
        add_action('parse_query', [$this, 'filterColumnsByMeta']);
        $this->settings->extendSettingsField('listing_screen_columns', function ($field) {
            $field['sub_fields'][] = [
                'key' => 'filterable',
                'label' => 'Filterable?',
                'name' => 'filterable',
                'type' => 'true_false',
            ];
            return $field;
        });
    }

    /**
     * Builds options list for all filterable columns
     *
     * @param string $post_type
     * @return array
     */
    protected function getColumnFilters(string $post_type): array
    {
        $post_type_config = Acf::findPostTypeConfig($post_type);
        if (!$post_type_config) {
            return [];
        }
        $filters = array_merge(
            $this->getCustomFilters($post_type_config),
            $this->getTaxonomyFilters($post_type_config),
        );
        $keys = array_column($filters, 'id');
        $filters = array_combine($keys, $filters);
        // Hook: sitchco/acf_post_type_admin_filters/filters
        $filters = apply_filters(static::hookName('filters'), $filters, $post_type_config);
        // Only use columns with at least 2 options
        return array_filter($filters, fn($filter) => count($filter['options']) > 1);
    }

    /**
     * Displays UI control for each filterable column
     *
     * @param string $post_type
     * @param string $render_location
     * @return array
     */
    public function renderColumnFilters(string $post_type, string $render_location): array
    {
        $filters = $this->getColumnFilters($post_type);
        // Only echo if render location is defined from WP admin execution,
        // to allow direct testing of filter options without echoed HTML
        if ($render_location) {
            foreach ($filters as $filter) {
                echo Template::getTemplateScoped(
                    SITCHCO_CORE_TEMPLATES_DIR . '/admin-filter-select.php',
                    compact('filter'),
                );
            }
        }
        return $filters;
    }

    /**
     * Builds filter options for meta columns
     *
     * @param array $post_type_config
     * @return array
     */
    protected function getCustomFilters(array $post_type_config): array
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $filters = [];

        foreach (static::getFilterableColumns($post_type_config) as $row) {
            $id = $row['name'];
            // Ignore taxonomy columns
            if (in_array($id, (array) $post_type_config['taxonomies'])) {
                continue;
            }
            // Get list of existing values in the db for this columns
            $field_values = $wpdb->get_results(
                $wpdb->prepare("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key=%s ORDER BY 1", $id),
            );
            if (empty($field_values)) {
                continue;
            }
            $filter = compact('id');
            // Default option
            $filter['options'][] = [
                'value' => '',
                'label' => "Filter by {$row['label']}",
                'selected' => false,
            ];
            // Derive each option's label from the same column_content/{column} filter chain that
            // renders the column cell, so a module writes one handler for both. Wrap the value to
            // match postMeta()'s array shape and maybe_unserialize() it so handlers see the same
            // input the cell path does; pass post_id 0 since there's no single post.
            array_walk($field_values, function ($el) use ($id, $post_type_config) {
                $value = maybe_unserialize($el->meta_value);
                $label = AcfPostTypeAdminColumns::renderColumnContent($id, [$value], 0, $post_type_config);
                $el->label = $label !== '' ? $label : (string) $el->meta_value;
            });
            // Hook: sitchco/acf_post_type_admin_filters/filter_values (optional array-level pass)
            $field_values = apply_filters(static::hookName('filter_values', $id), $field_values, $post_type_config);

            // Build options and determine whether value is currently selected
            foreach ($field_values as $field) {
                // Skip empty values (not '0', which is a valid value); they make no useful filter
                if ($field->meta_value === '' || $field->meta_value === null) {
                    continue;
                }
                $filter['options'][] = [
                    'value' => urlencode($field->meta_value),
                    'label' => $field->label,
                    'selected' => isset($_GET[$id]) && $_GET[$id] == urlencode($field->meta_value),
                ];
            }
            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * Builds filter options for taxonomy columns
     *
     * @param array $post_type_config
     * @return array
     */
    protected function getTaxonomyFilters(array $post_type_config): array
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $filters = [];
        $post_type = $post_type_config['post_type'];
        $taxonomies = array_filter((array) $post_type_config['taxonomies']);
        foreach ($taxonomies as $tax_slug) {
            $tax_obj = get_taxonomy($tax_slug);
            if (!$tax_obj->show_admin_column) {
                continue;
            }
            // Build the term list from terms actually attached to posts of this type across all
            // listing-visible statuses. Relying on get_terms()'s `hide_empty` would only count
            // published posts (the stored term count), hiding terms used solely by drafts, etc.
            $term_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT tt.term_id
                     FROM $wpdb->term_relationships tr
                     INNER JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     INNER JOIN $wpdb->posts p ON p.ID = tr.object_id
                     WHERE tt.taxonomy = %s
                       AND p.post_type = %s
                       AND p.post_status NOT IN ('trash', 'auto-draft')",
                    $tax_slug,
                    $post_type,
                ),
            );
            if (empty($term_ids)) {
                continue;
            }
            $terms = get_terms([
                'taxonomy' => $tax_slug,
                'include' => $term_ids,
                'hide_empty' => false, // safe: `include` already guarantees real usage
            ]);
            if (is_wp_error($terms)) {
                continue;
            }
            $filter = ['id' => $tax_slug];
            // Default options
            $filter['options'][] = [
                'value' => '',
                'label' => "All {$tax_obj->labels->name}",
                'selected' => false,
            ];
            // Build options and determine whether value is currently selected
            foreach ($terms as $term) {
                $filter['options'][] = [
                    'value' => $term->slug,
                    'label' => $term->name,
                    'selected' => isset($_GET[$tax_slug]) && $_GET[$tax_slug] == $term->slug,
                ];
            }
            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * Adds meta query to main admin listing query for selected filter values
     *
     * @param WP_Query $query
     * @return void
     */
    public function filterColumnsByMeta(WP_Query $query): void
    {
        global $pagenow;

        // Main query on admin edit listng screen
        if (!(is_admin() && $pagenow == 'edit.php' && $query->is_main_query())) {
            return;
        }
        $post_type_config = Acf::findPostTypeConfig($query->get('post_type'));
        // No post type configuration
        if (!$post_type_config) {
            return;
        }
        foreach (static::getFilterableColumns($post_type_config) as $row) {
            $id = $row['name'];
            if (($_GET[$id] ?? '') != '') {
                // Add key/value to the meta query to allow multiple meta filters
                $query->query_vars['meta_query'][] = ['key' => $id, 'value' => urldecode($_GET[$id])];
            }
        }
    }

    /**
     * Extracts and normalizes the filterable admin columns from the entire config
     *
     * @param array $post_type_config
     * @return array
     */
    public static function getFilterableColumns(array $post_type_config): array
    {
        return array_filter(
            AcfPostTypeAdminColumns::getColumnConfig($post_type_config),
            fn($row) => ($row['filterable'] ?? false) &&
                !in_array($row['name'], (array) $post_type_config['taxonomies']),
        );
    }
}
