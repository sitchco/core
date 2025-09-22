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

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetWPDependencies();
        $module = $this->container->get(ModuleTester::class);
        $this->prodAssets = new ModuleAssets($module, SITCHCO_DEV_HOT_FILE);
        $this->devAssets = new ModuleAssets($module, 'sitchco.config.php');
    }

    protected function resetWPDependencies(): void
    {
        unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles'], $GLOBALS['wp_script_modules']);
        $GLOBALS['wp_current_filter'] = [];
        wp_scripts();
        wp_styles();
        wp_script_modules();
        $GLOBALS['wp_current_filter'][] = 'enqueue_block_assets';
    }

    protected function getScriptModuleRegistered() {
        $ref = new ReflectionClass($GLOBALS['wp_script_modules']);
        $scriptModulesRegisteredProp = $ref->getProperty( 'registered' );
        return $scriptModulesRegisteredProp->getValue($GLOBALS['wp_script_modules']);
    }

    protected function assertViteClientEnqueued(?array $script_modules = null): void
    {
        if (empty($script_modules)) {
            $script_modules = $this->getScriptModuleRegistered();
        }
        $this->assertTrue($script_modules['fixtures/vite-client']['enqueue']);
        $this->assertEquals('https://example.org:5173/@vite/client', $script_modules['fixtures/vite-client']['src']);
    }

    public function test_creating_from_module()
    {
        $module = $this->container->get(ModuleTester::class);
        $this->assertEquals($module->assetsPath()->value(), $this->prodAssets->moduleAssetsPath->value());
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
        $this->assertStringEndsWith( '/fixtures/dist/assets/test-abcde.js', $registered->src);
        $this->devAssets->registerScript('test', 'test.js');
        $script = $this->getScriptModuleRegistered()['sitchco/test'];
        $this->assertFalse($script['enqueue']);
        $this->assertEquals('https://example.org:5173/assets/scripts/test.js', $script['src']);
    }

    public function test_enqueueScript()
    {
        $this->prodAssets->enqueueScript('test', 'test.js');
        wp_script_is('sitchco/test');
        $this->devAssets->enqueueScript('test', 'test.js');
        $script_modules = $this->getScriptModuleRegistered();
        $this->assertTrue($script_modules['sitchco/test']['enqueue']);
        $this->assertViteClientEnqueued($script_modules);
    }

    public function test_enqueueScript_with_dependencies()
    {
        $this->prodAssets->enqueueScript('test', 'test.js', ['sitchco/test-lib']);
        wp_script_is('sitchco/test');
        wp_script_is('sitchco/test-lib');
        $this->resetWPDependencies();
        $this->devAssets->registerScript('test-lib', 'test-lib.js');
        $this->devAssets->enqueueScript('test', 'test.js', ['jquery', 'sitchco/test-lib']);
        wp_script_is('jquery');
        $script_modules = $this->getScriptModuleRegistered();
        $this->assertTrue($script_modules['sitchco/test-lib']['enqueue']);
        $this->assertTrue($script_modules['sitchco/test']['enqueue']);
    }

    public function test_registerStyle()
    {
        $this->prodAssets->registerStyle('test', 'test.css');
        $registered = wp_styles()->registered['sitchco/test'];
        $this->assertStringEndsWith( '/fixtures/dist/assets/test-abcde.css', $registered->src);
        $this->resetWPDependencies();
        $this->devAssets->registerStyle('test', 'test.css');
        $registered = wp_styles()->registered['sitchco/test'];
        $this->assertEquals('https://example.org:5173/assets/styles/test.css', $registered->src);
    }

    public function test_enqueueStyle()
    {
        $this->prodAssets->enqueueStyle('test', 'test.css');
        wp_style_is('sitchco/test');
        $this->resetWPDependencies();
        $this->devAssets->enqueueStyle('test', 'test.css');
        $this->assertViteClientEnqueued();
    }

    public function test_enqueueBlockStyle()
    {
        $GLOBALS['wp_current_filter'][] = 'init';
        $this->prodAssets->enqueueBlockStyle('test-block', 'test-block.css');
        do_action('wp_enqueue_scripts');
        $registered = wp_styles()->registered['test-block'];
        $this->assertStringEndsWith('/fixtures/dist/assets/test-block-abcde.css', $registered->src);
        $this->assertStringEndsWith('/fixtures/assets/styles/test-block.css', $registered->extra['path']);
        $this->resetWPDependencies();
        $GLOBALS['wp_current_filter'][] = 'init';
        $this->devAssets->enqueueBlockStyle('test-block', 'test-block.css');
        $this->assertViteClientEnqueued();
    }

    public function test_inlineScript()
    {
        $inline_js = 'window.test = true';
        $this->prodAssets->enqueueScript('test', 'test.js');
        $this->prodAssets->inlineScript('test', $inline_js);
        $registered = wp_scripts()->registered['sitchco/test'];
        $this->assertEquals($inline_js, $registered->extra['before'][1]);
        $this->resetWPDependencies();
        $this->devAssets->enqueueScript('test', 'test.js');
        $this->devAssets->inlineScriptData('test', 'test', ['key' => 'value']);
        ob_start();
        do_action('wp_head');
        $html_out = ob_get_clean();
        $this->assertStringContainsString('<script>window.test = {"key":"value"};</script>', $html_out);
        $this->assertViteClientEnqueued();
    }

}
