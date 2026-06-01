<?php

namespace Sitchco\Tests\ModuleExtension;

use Sitchco\ModuleExtension\AcfPathsModuleExtension;
use Sitchco\Tests\Fakes\ModuleTester\ModuleTester;
use Sitchco\Tests\TestCase;

class AcfPathsModuleExtensionTest extends TestCase
{
    protected AcfPathsModuleExtension $extension;
    protected ModuleTester $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new ModuleTester();
        $this->extension = new AcfPathsModuleExtension();
        // extend() is the public seam that injects the active modules the
        // extension resolves save paths against.
        $this->extension->extend([$this->module]);
    }

    protected function tearDown(): void
    {
        // extend() registers global ACF filters; remove them so they don't leak
        // into other tests.
        remove_filter('acf/settings/load_json', [$this->extension, 'addModuleJsonPaths']);
        remove_filter('acf/json/save_paths', [$this->extension, 'setModuleJsonSavePaths'], 10);
        parent::tearDown();
    }

    public function test_redirects_save_path_to_module_when_field_group_json_exists(): void
    {
        // ModuleTester ships acf-json/group_moduletester.json, so saving that
        // field group should be redirected back into the module's folder.
        $result = $this->extension->setModuleJsonSavePaths(['/default/save/path'], ['key' => 'group_moduletester']);

        // The default path is discarded in favor of the owning module's folder.
        $this->assertCount(1, $result);
        // ACF requires a plain string path. A FilePath object now passes through
        // ACF un-cast and is rejected, so the filter must return a string.
        $this->assertIsString($result[0]);
        $this->assertEquals($this->module->path('acf-json')->value(), $result[0]);
    }

    public function test_adds_module_acf_json_path_to_load_paths_as_string(): void
    {
        $result = $this->extension->addModuleJsonPaths(['/existing/path']);

        // Incoming paths are preserved and the module's acf-json dir is added.
        $this->assertContains('/existing/path', $result);
        $this->assertContains($this->module->path('acf-json')->value(), $result);
        // ACF requires plain string paths; FilePath objects must be cast before
        // being returned from the filter.
        $this->assertContainsOnly('string', $result);
    }

    public function test_keeps_default_save_paths_when_no_module_owns_the_field_group(): void
    {
        $defaultPaths = ['/default/save/path'];

        // No module has acf-json/group_unowned.json, so the default save paths
        // should pass through unchanged.
        $result = $this->extension->setModuleJsonSavePaths($defaultPaths, ['key' => 'group_unowned']);

        $this->assertSame($defaultPaths, $result);
    }

    public function test_keeps_default_save_paths_when_post_has_no_key(): void
    {
        $defaultPaths = ['/default/save/path'];

        $result = $this->extension->setModuleJsonSavePaths($defaultPaths, []);

        $this->assertSame($defaultPaths, $result);
    }
}
