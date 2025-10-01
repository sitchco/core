<?php

namespace Sitchco\Tests\Support;

use Sitchco\Support\FilePath;
use Sitchco\Tests\TestCase;

class FilePathTest extends TestCase
{
    const FILE = SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg';

    public function test_construct_and_value_and_toString()
    {
        $fp = new FilePath(static::FILE);
        $this->assertSame(static::FILE, $fp->value());
        $this->assertSame(static::FILE, (string) $fp);
    }

    public function test_append_and_parent()
    {
        $dir = dirname(static::FILE);
        $fp2 = FilePath::create($dir)->append('child.txt');
        $this->assertStringEndsWith('child.txt', $fp2->value());
        $this->assertSame($dir . '/', $fp2->parent()->value());
    }

    public function test_findAncestor()
    {
        $fp = new FilePath(static::FILE);
        $ancestor = $fp->findAncestor('composer.json');
        $this->assertEquals(SITCHCO_CORE_DIR . '/', $ancestor->value());
    }

    public function test_exists_and_isFile_and_isDir()
    {
        $file = new FilePath(static::FILE);
        $this->assertTrue($file->exists());
        $this->assertTrue($file->isFile());
        $this->assertFalse($file->isDir());

        $dir = $file->parent();
        $this->assertTrue($dir->exists());
        $this->assertTrue($dir->isDir());
        $this->assertFalse($dir->isFile());
    }

    public function test_isRoot()
    {
        $root = new FilePath(DIRECTORY_SEPARATOR);
        $this->assertTrue($root->isRoot());

        $notRoot = new FilePath(static::FILE);
        $this->assertFalse($notRoot->isRoot());
    }

    public function test_name_and_dir()
    {
        $fp = new FilePath(static::FILE);
        $this->assertSame('sample-image', $fp->name());
        $this->assertSame(dirname(static::FILE), $fp->dir());
    }

    public function test_relativeTo()
    {
        $dir = dirname(static::FILE);
        $fp = new FilePath(static::FILE);

        $rel = $fp->relativeTo(new FilePath($dir));
        $this->assertSame('sample-image.jpg', $rel);
    }

    public function test_glob()
    {
        $dir = new FilePath(dirname(static::FILE));
        $results = $dir->glob('*.jpg');
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertInstanceOf(FilePath::class, $results[0]);
    }

    public function test_url()
    {
        $fp = new FilePath(static::FILE);
        $url = $fp->url();
        $this->assertIsString($url);
        $this->assertStringContainsString('sample-image.jpg', $url);
    }
}
