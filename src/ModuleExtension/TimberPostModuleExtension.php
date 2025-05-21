<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\Module;
use Sitchco\Model\PostBase;
use Sitchco\Utils\ArrayUtil;

class TimberPostModuleExtension implements ModuleExtension
{

    /**
     * @param Module[] $modules
     * @return void
     */
    public function extend(array $modules): void
    {
        add_filter('timber/post/classmap', function($classmap) use ($modules) {
            $post_classnames = ArrayUtil::arrayMapFlat(function(Module $module) {
                return $module::POST_CLASSES;
            }, $modules);
            $valid_post_classnames = array_filter($post_classnames, fn($c) => is_subclass_of($c, PostBase::class));
            foreach ($valid_post_classnames as $classname) {
                $classmap[$classname::POST_TYPE] = $classname;
            }
            return $classmap;
        });
    }
}
