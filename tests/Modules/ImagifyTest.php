<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\Imagify;
use Sitchco\Tests\TestCase;

/**
 * Class ImagifyTest
 * @package Sitchco\Tests\Integration
 */
class ImagifyTest extends TestCase
{
    private Imagify $imagify;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imagify = $this->container->get(Imagify::class);
    }

    public function testInitAddsFilter()
    {
        $this->assertNotFalse(has_filter('imagify_site_root', [$this->imagify, 'setSiteRoot']));
    }

    public function testSetSiteRootWithWpContent()
    {
        $uploadBaseDir = imagify_get_filesystem()->get_upload_basedir(true);

        if (str_contains($uploadBaseDir, '/wp-content/')) {
            [$expectedBaseDir] = explode('/wp-content/', $uploadBaseDir);
            $expectedBaseDir = trailingslashit($expectedBaseDir);
        } else {
            $expectedBaseDir = $uploadBaseDir;
        }

        $rootPath = '/var/www/html/';
        $this->assertSame($expectedBaseDir, $this->imagify->setSiteRoot($rootPath));
    }

    public function testSetSiteRootWithoutWpContent()
    {
        add_filter('upload_dir', function ($dirs) {
            $dirs['basedir'] = '/var/www/custom-uploads';
            return $dirs;
        });

        $modifiedUploadBaseDir = imagify_get_filesystem()->get_upload_basedir(true);

        $rootPath = '/var/www/html/';
        $expected = str_contains($modifiedUploadBaseDir, '/wp-content/')
            ? trailingslashit(explode('/wp-content/', $modifiedUploadBaseDir)[0])
            : $rootPath;
        $this->assertSame($expected, $this->imagify->setSiteRoot($rootPath));

        remove_filter('upload_dir', fn($dirs) => $dirs);
    }
}
