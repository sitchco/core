<?php

namespace Sitchco\Tests\Integration;

use Sitchco\Integration\Imagify;
use Sitchco\Tests\Support\TestCase;

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
        $this->imagify = new Imagify();
        $this->imagify->init(); // Call init() to register the filter
    }

    public function testInitAddsFilter()
    {
        // Ensure the filter is added after calling init()
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
        $expected = str_contains($modifiedUploadBaseDir, '/wp-content/') ? trailingslashit(explode('/wp-content/', $modifiedUploadBaseDir)[0]) : $rootPath;
        $this->assertSame($expected, $this->imagify->setSiteRoot($rootPath));

        remove_filter('upload_dir', fn($dirs) => $dirs);
    }

//    public function testSetSiteRootWithoutWpContent()
//    {
//        // Simulate an upload directory outside `/wp-content/`
//        add_filter('upload_dir', function ($dirs) {
//            $dirs['basedir'] = '/var/www/custom-uploads';
//            return $dirs;
//        });
//
//        // Force WordPress to re-fetch the upload base directory
//        $modifiedUploadBaseDir = imagify_get_filesystem()->get_upload_basedir(true);
//
//        $rootPath = '/var/www/html/';
//        if (!str_contains($modifiedUploadBaseDir, '/wp-content/')) {
//            $this->assertSame($rootPath, $this->imagify->setSiteRoot($rootPath));
//        }
//
//        // Cleanup: Remove the filter after the test
//        remove_filter('upload_dir', fn($dirs) => $dirs);
//
//        // Fallback assertion to prevent PHPUnit from marking it as risky
//        $this->assertTrue(true);
//    }
}
