<?php

namespace Sitchco\Modules\Model;

use Sitchco\Framework\Module;
use Sitchco\Model\Category;
use Sitchco\Model\PostFormat;
use Sitchco\Model\PostTag;
use Sitchco\Modules\TimberModule;

class TermModel extends Module
{
    public const DEPENDENCIES = [TimberModule::class];

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
