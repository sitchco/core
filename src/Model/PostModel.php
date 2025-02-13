<?php

namespace Sitchco\Model;

use Sitchco\Collection\Collection;
use Sitchco\Framework\Core\Module;
use Sitchco\Integration\Timber;
use Timber\PostQuery;

class PostModel extends Module
{
    public const DEPENDENCIES = [
        Timber::class
    ];

    public function init(): void
    {
        add_filter('timber/post/classmap', [$this, 'addCustomPostClassmap']);
        add_filter('timber/posts', [$this, 'convertToCollection']);
    }

    /**
     * Add custom post class mapping for Timber.
     *
     * @param array $classmap Existing class map.
     * @return array Updated class map.
     */
    public function addCustomPostClassmap(array $classmap): array
    {
        $classmap[Post::POST_TYPE] = Post::class;
        $classmap[Page::POST_TYPE] = Page::class;
        return $classmap;
    }

    public function convertToCollection($posts, $query): Collection
    {
        if ($query instanceof \WP_Query) {
            $postQuery = new PostQuery($query);
            return new Collection($postQuery);
        }
        return $posts;
    }
}