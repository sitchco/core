<?php

namespace Sitchco\Utils;

/**
 * class Acf
 * @package Sitchco\Utils
 */
class Acf
{
    public static function clearPostStore($post_id): void
    {
        $acf_store = acf_get_store('values');
        $acf_store->data = array_filter($acf_store->data, function($key) use ($post_id) {
            return !str_contains($key, "$post_id");
        }, ARRAY_FILTER_USE_KEY);
    }
}