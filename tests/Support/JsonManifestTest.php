<?php

namespace Sitchco\Tests\Support;

use Sitchco\Support\JsonManifest;
use JsonException;

/**
 * Class JsonManifestTest
 * @package Sitchco\Tests\Support
 */
class JsonManifestTest extends TestCase
{
    private string $fixtureManifestPath;

    /**
     * Set up the test environment.
     *
     * Defines the path to the fixture JSON manifest file.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Path to the fixture JSON manifest file
        $this->fixtureManifestPath = dirname(__DIR__, 1) . '/fixtures/test-manifest.json';
//        error_log($this->fixtureManifestPath);
    }

    /**
     * Test the constructor with a valid JSON manifest file.
     *
     * @throws JsonException
     */
    public function testConstructorWithValidFile(): void
    {
        $manifest = new JsonManifest($this->fixtureManifestPath);

        $this->assertIsArray($manifest->get());
        $this->assertEquals('Test App', $manifest->getPath('name'));
    }

//    /**
//     * Test the constructor with an invalid JSON manifest file.
//     */
//    public function testConstructorWithInvalidFile(): void
//    {
//        $invalidManifestPath = dirname(__DIR__) . '/fixtures/invalid-manifest.json';
//
//        $this->expectException(JsonException::class);
//        new JsonManifest($invalidManifestPath);
//    }
//
//    /**
//     * Test the constructor with a non-existent JSON manifest file.
//     *
//     * @throws JsonException
//     */
//    public function testConstructorWithNonExistentFile(): void
//    {
//        $nonExistentPath = dirname(__DIR__) . '/Fixtures/non-existent.json';
//        $manifest = new JsonManifest($nonExistentPath);
//
//        $this->assertIsArray($manifest->get());
//        $this->assertEmpty($manifest->get());
//    }
//
//    /**
//     * Test the `get` method.
//     *
//     * @throws JsonException
//     */
//    public function testGet(): void
//    {
//        $manifest = new JsonManifest($this->fixtureManifestPath);
//        $data = $manifest->get();
//
//        $this->assertIsArray($data);
//        $this->assertEquals('Test App', $data['name']);
//        $this->assertEquals('1.0.0', $data['version']);
//    }
//
//    /**
//     * Test the `getPath` method with a top-level key.
//     *
//     * @throws JsonException
//     */
//    public function testGetPathTopLevelKey(): void
//    {
//        $manifest = new JsonManifest($this->fixtureManifestPath);
//
//        $this->assertEquals('Test App', $manifest->getPath('name'));
//        $this->assertEquals('1.0.0', $manifest->getPath('version'));
//    }
//
//    /**
//     * Test the `getPath` method with a nested key.
//     *
//     * @throws JsonException
//     */
//    public function testGetPathNestedKey(): void
//    {
//        $manifest = new JsonManifest($this->fixtureManifestPath);
//
//        $this->assertEquals('^8.0', $manifest->getPath('dependencies.php'));
//        $this->assertEquals('phpunit', $manifest->getPath('scripts.test'));
//    }
//
//    /**
//     * Test the `getPath` method with a non-existent key.
//     *
//     * @throws JsonException
//     */
//    public function testGetPathNonExistentKey(): void
//    {
//        $manifest = new JsonManifest($this->fixtureManifestPath);
//
//        $this->assertNull($manifest->getPath('non.existent.key'));
//        $this->assertEquals('default', $manifest->getPath('non.existent.key', 'default'));
//    }
//
//    /**
//     * Test the `getPath` method with an empty key.
//     *
//     * @throws JsonException
//     */
//    public function testGetPathEmptyKey(): void
//    {
//        $manifest = new JsonManifest($this->fixtureManifestPath);
//
//        $this->assertEquals($manifest->get(), $manifest->getPath(''));
//    }
}