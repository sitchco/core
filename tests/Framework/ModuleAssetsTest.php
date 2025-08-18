<?php

namespace Sitchco\Tests\Framework;

use ReflectionClass;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Tests\Fakes\ModuleTester;
use Sitchco\Tests\TestCase;

class ModuleAssetsTest extends TestCase
{
    protected ModuleAssets $prodAssets;
    protected ModuleAssets $devAssets;

    protected \ReflectionProperty $scriptModulesRegisteredProp;

    protected function setUp(): void
    {
        parent::setUp();
        $module = $this->container->get(ModuleTester::class);
        $this->prodAssets = new ModuleAssets($module, SITCHCO_DEV_HOT_FILE);
        $this->devAssets = new ModuleAssets($module, 'sitchco.config.php');
        $ref = new ReflectionClass($GLOBALS['wp_script_modules']);
        $this->scriptModulesRegisteredProp = $ref->getProperty( 'registered' );
    }

    protected function getScriptModuleRegistered() {
        return $this->scriptModulesRegisteredProp->getValue($GLOBALS['wp_script_modules']);
    }

    public function test_creating_from_module()
    {
        $module = $this->container->get(ModuleTester::class);
        $this->assertEquals($module->assetsPath()->value(), $this->prodAssets->modulePath->value());
        $this->assertEquals(SITCHCO_CORE_FIXTURES_DIR . '/', $this->prodAssets->productionBuildPath->value());
        $this->assertFalse($this->prodAssets->isDevServer);
        $this->assertNull($this->prodAssets->devBuildPath);
        $this->assertTrue($this->devAssets->isDevServer);
        $this->assertEquals(SITCHCO_CORE_FIXTURES_DIR . '/', $this->devAssets->devBuildPath);
        $this->assertEquals('https://example.org:5173', $this->devAssets->devBuildUrl);
    }

    public function test_registerScript()
    {
        $this->prodAssets->registerScript('test', 'test.js');
        $registered = wp_scripts()->registered['sitchco/test'];
        $this->assertEquals( 'http://example.org/wp-content/mu-plugins/sitchco-core/tests/fixtures/dist/assets/test-abcde.js', $registered->src);
        $this->devAssets->registerScript('test', 'test.js');
        $script = $this->getScriptModuleRegistered()['sitchco/test'];
        $this->assertEquals('https://example.org:5173/assets/scripts/test.js', $script['src']);
    }

}
