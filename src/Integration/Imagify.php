<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;

/**
 * Class Imagify
 * @package Sitchco\Integration
 *
 * TODO: disable on local/staging
 */
class Imagify extends Module
{
    /**
     * Initialize the Imagify integration.
     * @return void
     */
    public function init(): void
    {
        if ($this->isImagifyActivated()) {
            add_filter('imagify_site_root', [$this, 'setSiteRoot'], 10001);
        }
    }

    /**
     * Check if the Imagify plugin is activated.
     *
     * @return bool True if Imagify is activated, false otherwise.
     */
    private function isImagifyActivated(): bool
    {
        return class_exists('Imagify');
    }

    /**
     * Set the site root path for Imagify.
     *
     * @param string $rootPath The original root path.
     * @return string The modified root path.
     */
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