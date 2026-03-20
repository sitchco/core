<?php

namespace Sitchco\Modules\ContentTargeting;

use Sitchco\Framework\Module;
use Sitchco\Utils\WordPress;

class ContentTargeting extends Module
{
    public const HOOK_SUFFIX = 'content-targeting';

    public function init(): void
    {
        add_filter('acf/load_field/key=field_ct_pages', [$this, 'filterPagePostTypes']);
        add_filter('acf/load_field/key=field_ct_archives', [$this, 'loadArchiveChoices']);
    }

    public function filterPagePostTypes(array $field): array
    {
        $field['post_type'] = WordPress::getVisibleSinglePostTypes(true);
        return $field;
    }

    public function loadArchiveChoices(array $field): array
    {
        $field['choices'] = $this->getArchiveChoices();
        return $field;
    }

    public function matchesCurrentRequest(array $config): bool
    {
        $mode = $config['mode'] ?? 'exclude';
        $pages = $config['pages'] ?? [];
        $archives = $config['archives'] ?? [];

        if (empty($pages) && empty($archives)) {
            return true;
        }

        $matched = is_singular() ? in_array(get_queried_object_id(), $pages, true) : $this->matchesArchive($archives);

        return $mode === 'include' ? $matched : !$matched;
    }

    private function matchesArchive(array $archives): bool
    {
        foreach ($archives as $key) {
            if ($key === 'posts_index' && is_home()) {
                return true;
            }
            if ($key === 'search_results' && is_search()) {
                return true;
            }
            if ($key === 'author_archive' && is_author()) {
                return true;
            }
            if (str_starts_with($key, 'post_type_archive:') && is_post_type_archive(substr($key, 18))) {
                return true;
            }
            if (str_starts_with($key, 'taxonomy_archive:')) {
                $tax = substr($key, 17);
                if (($tax === 'category' && is_category()) || ($tax === 'post_tag' && is_tag()) || is_tax($tax)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getArchiveChoices(): array
    {
        $choices = ['posts_index' => 'Posts Index'];
        foreach (WordPress::getVisibleArchivePostTypes() as $type) {
            $obj = get_post_type_object($type);
            $choices["post_type_archive:{$type}"] = "Archive: {$obj->labels->name}";
        }
        foreach (get_taxonomies([], 'objects') as $tax) {
            if (is_taxonomy_viewable($tax)) {
                $choices["taxonomy_archive:{$tax->name}"] = "Taxonomy: {$tax->labels->name}";
            }
        }
        $choices['author_archive'] = 'Author Archives';
        $choices['search_results'] = 'Search Results';
        return $choices;
    }
}
