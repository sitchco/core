<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;

/**
 * Class Imagify
 * @package Sitchco\Integration
 */
class Imagify extends Module
{
    public function init(): void
    {
        add_filter('imagify_site_root', [$this, 'setSiteRoot'], 10001);
    }

    public function setSiteRoot(string $rootPath): string
    {
        $uploadBaseDir = imagify_get_filesystem()->get_upload_basedir(true);

        if (!str_contains($uploadBaseDir, '/wp-content/')) {
            return $rootPath;
        }

        [$uploadBaseDir] = explode('/wp-content/', $uploadBaseDir);

        return trailingslashit($uploadBaseDir);
    }
}
