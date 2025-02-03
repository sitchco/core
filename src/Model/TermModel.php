<?php

namespace Sitchco\Model;

use Sitchco\Framework\Core\Module;
use Sitchco\Integration\Timber;

class TermModel extends Module
{
    public const DEPENDENCIES = [
        Timber::class
    ];

    public function init(): void
    {
        add_filter('timber/term/classmap', [$this, 'addCustomTermClassmap']);
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