<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;
use Sitchco\Model\Post;

/**
 * class Timber
 * @package Sitchco\Integration
 */
class Timber extends Module
{
    public function __construct()
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
        return $classmap;
    }
}