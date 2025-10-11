<?php

namespace Sitchco\Utils;

use ACF_Post_Type;
use ACF_Taxonomy;
use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;

/**
 * Class Acf
 * @package Sitchco\Utils
 */
class Acf
{
    /**
     * Clears the ACF store values associated with a given post ID.
     *
     * @param int $post_id The post ID to clear the ACF store for.
     * @return void
     */
    public static function clearPostStore(int $post_id): void
    {
        $acf_store = acf_get_store('values');
        $acf_store->data = array_filter(
            $acf_store->data,
            fn(string $key): bool => !str_contains($key, (string) $post_id),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Returns the instance of the ACF Post Type.
     *
     * @return ACF_Post_Type
     */
    public static function postTypeInstance(): ACF_Post_Type
    {
        $acf_post_type = acf_get_instance('ACF_Post_Type'); /* @var $acf_post_type ACF_Post_Type */
        return $acf_post_type;
    }

    public static function taxonomyInstance(): ACF_Taxonomy
    {
        $acf_taxonomy = acf_get_instance('ACF_Taxonomy'); /* @var $acf_taxonomy ACF_Taxonomy */
        return $acf_taxonomy;
    }

    /**
     * Finds and returns all configurations for the post types.
     *
     * @return array An array of post type configurations.
     */
    public static function findAllPostTypeConfigs(): array
    {
        return self::postTypeInstance()->get_posts();
    }

    /**
     * Finds the configuration for a specific post type.
     * Avoids recursion when the post type is the same as the current ACF Post Type.
     *
     * @param array|string $post_type The post type or post types to search for.
     * @return array|null The configuration of the post type, or null if not found.
     */
    public static function findPostTypeConfig(array|string $post_type): ?array
    {
        $acf_post_type = self::postTypeInstance();

        // Prevent infinite recursion
        if ($post_type === $acf_post_type->post_type) {
            return null;
        }

        foreach (self::findAllPostTypeConfigs() as $post) {
            if ($post['post_type'] === $post_type) {
                return $post;
            }
        }

        return null;
    }

    /**
     * Merges and filters ACF field attributes with additional attributes.
     * Returns only non-null and non-empty values.
     *
     * @param array $field The ACF field data.
     * @param array $atts Additional attributes to merge with the field.
     * @return array The merged attributes array.
     */
    public static function linkToAttr(array $field, array $atts = []): array
    {
        return array_filter(
            [...$field, ...$atts, 'href' => $field['url'] ?? ''],
            fn($value) => !is_null($value) && $value !== '',
        );
    }

    /**
     * Generates an HTML <a> element with the given field attributes and optional text.
     *
     * @param array $field The ACF field data for generating the link.
     * @param array $atts Additional attributes to apply to the link.
     * @param string|null $text The link text. If null, the field title is used.
     * @return string The generated <a> element HTML.
     */
    public static function linkToEl(array $field, array $atts = [], ?string $text = null): string
    {
        $field = self::linkToAttr($field, $atts);

        if ($text === null && !empty($field['title'])) {
            $text = $field['title'];
            unset($field['title']); // Remove title attr for accessibility
        }

        return sprintf('<a %s>%s</a>', ArrayUtil::toAttributes($field), htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Filter a list of modules by whether it has a relative acf-json folder
     * an relative acf-json directory is present
     *
     * @param Module[] $modules
     * @return Module[]
     */

    public static function findModulesWithJsonPath(array $modules): array
    {
        return array_filter($modules, function ($module) {
            return $module->path('acf-json')->isDir();
        });
    }

    /**
     * Given a set of FilePath instances referencing acf-json directories and an ACF entity key,
     * find the directory where the given entity is saved
     *
     * @param FilePath[] $paths
     * @param string $key
     * @return FilePath|null
     */
    public static function findJsonFile(array $paths, string $key): ?FilePath
    {
        $fieldGroupKey = sanitize_text_field($key);

        foreach ($paths as $jsonPath) {
            $possibleFile = $jsonPath->append($fieldGroupKey . '.json');
            if ($possibleFile->exists()) {
                return $jsonPath;
            }
        }
        return null;
    }
}
