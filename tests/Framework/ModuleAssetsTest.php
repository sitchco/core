<?php

namespace Sitchco\Tests\Framework;

use ReflectionClass;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Tests\Fakes\ModuleTester\ModuleTester;
use Sitchco\Tests\TestCase;
use WP_Hook;

class ModuleAssetsTest extends TestCase
{
    protected ModuleAssets $prodAssets;
    protected ModuleAssets $devAssets;

    protected function setUp(): void
    {
        parent::setUp();
        add_theme_support('html5', ['script', 'style']);
        $this->resetWPDependencies();
        $module = $this->container->get(ModuleTester::class);
        $this->prodAssets = new ModuleAssets($module, SITCHCO_DEV_HOT_FILE);
        $this->devAssets = new ModuleAssets($module, 'sitchco.config.php');
    }

    protected function resetWPDependencies(): void
    {
        unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles'], $GLOBALS['wp_script_modules']);
        remove_all_actions('wp_footer');
        /* @var WP_Hook $wp_hook */
        $wp_hook = $GLOBALS['wp_filter']['enqueue_block_assets'] ?? null;
        if ($wp_hook) {
            $callbacks = &$wp_hook->callbacks[10];
        } else {
            $callbacks = [];
        }
        foreach ($callbacks as $key => $callback) {
            if ($callback['function'] instanceof \Closure) {
                unset($callbacks[$key]);
            }
        }
        $GLOBALS['wp_current_filter'] = [];
        wp_scripts();
        wp_styles();
        wp_script_modules();
        $GLOBALS['wp_current_filter'][] = 'enqueue_block_assets';
    }

    /**
     * Get script module registration and queue state.
     *
     * This method handles compatibility across WordPress versions:
     * - WP 6.5+ has a separate 'queue' property for enqueued modules
     * - Earlier versions only have 'registered' with inline 'enqueue' flags
     *
     * The reflection approach is necessary because WP_Script_Modules properties
     * are private and no public accessor methods exist for test inspection.
     *
     * @return array{registered: array, queue: array}
     */
    protected function getScriptModuleState(): array
    {
        $scriptModules = $GLOBALS['wp_script_modules'];
        $reflection = new ReflectionClass($scriptModules);
        $registeredProperty = $reflection->getProperty('registered');
        $registered = (array) $registeredProperty->getValue($scriptModules);

        // WP 6.5+ has a separate queue property
        if ($reflection->hasProperty('queue')) {
            $queueProperty = $reflection->getProperty('queue');
            $queue = (array) $queueProperty->getValue($scriptModules);
        } else {
            // Earlier versions: build queue from 'enqueue' flags in registered modules
            $queue = [];
            foreach ($registered as $moduleId => $module) {
                if (!empty($module['enqueue'] ?? false)) {
                    $queue[] = $moduleId;
                }
            }
        }

        return [
            'registered' => $registered,
            'queue' => $queue,
        ];
    }

    protected function assertViteClientEnqueued(?array $script_modules = null): void
    {
        if (empty($script_modules)) {
            $script_modules = $this->getScriptModuleState();
        }

        $this->assertContains('tests/vite-client', (array) $script_modules['queue']);
        $this->assertEquals(
            'https://example.org:5173/@vite/client',
            $script_modules['registered']['tests/vite-client']['src'],
        );
    }

    protected function assertAssetOutputs(\WP_Dependencies $deps, string $handle, string $expected): void
    {
        $this->assertTrue($deps->query($handle, 'enqueued'));
        ob_start();
        $deps->do_item($handle);
        $output = ob_get_clean();
        $this->assertStringStartsWith($expected, $output);
    }

    public function test_creating_from_module()
    {
        $module = $this->container->get(ModuleTester::class);
        $this->assertEquals($module->assetsPath()->value(), $this->prodAssets->moduleAssetsPath->value());
        $this->assertEquals(SITCHCO_CORE_TESTS_DIR . '/', $this->prodAssets->productionBuildPath->value());
        $this->assertFalse($this->prodAssets->isDevServer);
        $this->assertNull($this->prodAssets->devBuildPath);
        $this->assertTrue($this->devAssets->isDevServer);
        $this->assertEquals(SITCHCO_CORE_TESTS_DIR . '/', $this->devAssets->devBuildPath);
        $this->assertEquals('https://example.org:5173', $this->devAssets->devBuildUrl);
    }

    public function test_registerScript()
    {
        $this->prodAssets->registerScript('test', 'test.js');
        $registered = wp_scripts()->registered['sitchco/test'];
        $this->assertStringEndsWith('dist/assets/test-abcde.js', $registered->src);
        $this->resetWPDependencies();
        $this->devAssets->registerScript('test', 'test.js');
        $registered = wp_scripts()->registered['sitchco/test'];
        $this->assertEquals('https://example.org:5173/Fakes/ModuleTester/assets/scripts/test.js', $registered->src);
    }

    public function test_enqueueScript()
    {
        $this->prodAssets->enqueueScript('test', 'test.js');
        $this->assertAssetOutputs(
            wp_scripts(),
            'sitchco/test',
            '<script src="http://example.org/wp-content/mu-plugins/sitchco-core/tests/dist/assets/test-abcde.js" id="sitchco/test-js">',
        );
        $this->resetWPDependencies();
        $this->devAssets->enqueueScript('test', 'test.js');
        $this->assertAssetOutputs(
            wp_scripts(),
            'sitchco/test',
            '<script src="https://example.org:5173/Fakes/ModuleTester/assets/scripts/test.js" id="sitchco/test-js" type="module">',
        );
        $this->assertViteClientEnqueued();
    }

    public function test_registerStyle()
    {
        $this->prodAssets->registerStyle('test', 'test.css');
        $registered = wp_styles()->registered['sitchco/test'];
        $this->assertStringEndsWith('dist/assets/test-abcde.css', $registered->src);
        $this->resetWPDependencies();
        $this->devAssets->registerStyle('test', 'test.css');
        $registered = wp_styles()->registered['sitchco/test'];
        $this->assertEquals('https://example.org:5173/Fakes/ModuleTester/assets/styles/test.css', $registered->src);
    }

    public function test_enqueueStyle()
    {
        $this->prodAssets->enqueueStyle('test', 'test.css');
        $this->assertTrue(wp_style_is('sitchco/test'));
        $this->resetWPDependencies();
        $this->devAssets->enqueueStyle('test', 'test.css');
        $this->assertViteClientEnqueued();
    }

    public function test_enqueueBlockStyle()
    {
        $handle = 'sitchco/test-block';
        $GLOBALS['wp_current_filter'][] = 'init';
        add_filter('should_load_block_assets_on_demand', '__return_false');
        $this->prodAssets->enqueueBlockStyle($handle, 'test-block.css');
        do_action('wp_enqueue_scripts');
        $registered = wp_styles()->registered[$handle];
        $this->assertStringEndsWith('dist/assets/test-block-abcde.css', $registered->src);
        $this->assertStringEndsWith('assets/styles/test-block.css', $registered->extra['path']);
        $this->assertAssetOutputs(
            wp_styles(),
            $handle,
            "<link rel='stylesheet' id='sitchco/test-block-css' href='http://example.org/wp-content/mu-plugins/sitchco-core/tests/dist/assets/test-block-abcde.css' media='all' />",
        );
        $this->resetWPDependencies();
        $GLOBALS['wp_current_filter'][] = 'init';
        $this->devAssets->enqueueBlockStyle($handle, 'test-block.css');
        do_action('wp_enqueue_scripts');
        $registered = wp_styles()->registered[$handle];
        $this->assertStringEndsWith(
            'https://example.org:5173/Fakes/ModuleTester/assets/styles/test-block.css',
            $registered->src,
        );
        $this->assertStringEndsWith('assets/styles/test-block.css', $registered->extra['path']);
        $this->assertAssetOutputs(
            wp_styles(),
            $handle,
            "<link rel='stylesheet' id='sitchco/test-block-css' href='https://example.org:5173/Fakes/ModuleTester/assets/styles/test-block.css' media='all' />",
        );
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
        $this->assertStringContainsString(
            '<script>window.sitchco = window.sitchco || {}; window.sitchco.test = {"key":"value"};</script>',
            $html_out,
        );
        $this->assertViteClientEnqueued();
    }
}
