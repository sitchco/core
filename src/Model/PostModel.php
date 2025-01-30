<?php

namespace Sitchco\Model;

use Sitchco\Framework\Core\Module;
use Sitchco\Integration\Timber;

class PostModel extends Module
{
    public const DEPENDENCIES = [
        Timber::class
    ];

    public function init(): void
    {
        add_filter('timber/post/classmap', [$this, 'addCustomPostClassmap']);
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
}