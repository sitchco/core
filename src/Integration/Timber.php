<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;
use Sitchco\Model\Category;
use Sitchco\Model\Page;
use Sitchco\Model\Post;
use Sitchco\Model\PostFormat;
use Sitchco\Model\PostTag;

/**
 * class Timber
 * @package Sitchco\Integration
 */
class Timber extends Module
{
    public function init()
    {
        if (class_exists('Timber\Timber')) {
            \Timber\Timber::init();
            $this->registerFilters();
        }
    }

    /**
     * Register WordPress filters related to Timber.
     */
    private function registerFilters(): void
    {
        add_filter('timber/post/classmap', [$this, 'addCustomPostClassmap']);
        add_filter('timber/term/classmap', [$this, 'addCustomTermClassmap']);
    }

    /**
     * Add custom post class mapping for Timber.
     *
     * @param array $classmap Existing class map.
     * @return array Updated class map.
     */
    public function addCustomPostClassmap(array $classmap): array
    {
        $classmap['post'] = Post::class;
        $classmap['page'] = Page::class;
        return $classmap;
    }

    /**
     * Add custom term class mapping for Timber.
     *
     * @param array $classmap Existing class map.
     * @return array Updated class map.
     */
    public function addCustomTermClassmap(array $classmap): array
    {
        $classmap['category'] = Category::class;
        $classmap['post_tag'] = PostTag::class;
        $classmap['post_format'] = PostFormat::class;
        return $classmap;
    }
}