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

    public const POST_CLASSES = [
        Post::class,
        Page::class,
    ];
    public function init(): void
    {
        add_filter('timber/posts', [$this, 'convertToCollection']);
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