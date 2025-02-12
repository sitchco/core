<?php

namespace Sitchco\Utils;

use ACF_Post_Type;

/**
 * class Acf
 * @package Sitchco\Utils
 */
class Acf
{
    public static function clearPostStore($post_id): void
    {
        $acf_store = acf_get_store('values');
        $acf_store->data = array_filter($acf_store->data, function ($key) use ($post_id) {
            return !str_contains($key, "$post_id");
        }, ARRAY_FILTER_USE_KEY);
    }

    public static function postTypeInstance(): ACF_Post_Type
    {
        $acf_post_type = acf_get_instance('ACF_Post_Type'); /* @var $acf_post_type ACF_Post_Type */
        return $acf_post_type;
    }

    public static function findAllPostTypeConfigs(): array
    {
        return static::postTypeInstance()->get_posts();
    }

    public static function findPostTypeConfig(string $post_type): array|null
    {
        $acf_post_type = static::postTypeInstance();
        // prevent infinite recursion
        if ($post_type == $acf_post_type->post_type) {
            return null;
        }
        $acf_post_type_posts = static::findAllPostTypeConfigs();
        foreach ($acf_post_type_posts as $post) {
            if ($post['post_type'] === $post_type) {
                return $post;
            }
        }
        return null;
    }
}