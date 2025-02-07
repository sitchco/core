<?php

namespace Sitchco\Tests;

use DI\DependencyException;
use DI\NotFoundException;
use Sitchco\Events\SavePermalinksAsyncHook;
use Sitchco\Framework\Config\ModuleConfigLoader;
use Sitchco\Framework\Core\Registry;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Timber;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;
use Sitchco\Model\PostModel;
use Sitchco\Model\TermModel;
use Sitchco\Integration\AdvancedCustomFields\CustomPostTypes;
use Sitchco\Tests\Support\ModuleTester;
use Sitchco\Tests\Support\TestCase;

class CoreMuPluginTest extends TestCase
{
    function test_registers_and_activates_a_module()
    {
        $loaded_config = $this->container->get(ModuleConfigLoader::class)->load();
        $this->assertEquals([
            'featureOne' => true,
            'featureTwo' => true,
            'featureThree' => false,
        ], $loaded_config[ModuleTester::class]);
        $full_feature_list = $this->container->get(Registry::class)->getModuleFeatures();
        $this->assertEquals([
            'featureOne',
            'featureTwo',
            'featureThree',
        ], $full_feature_list[ModuleTester::class]);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    function test_active_module_initialization()
    {
        $active_modules = $this->container->get(Registry::class)->getActiveModules();
        $ModuleInstance = $this->container->get(ModuleTester::class);
        $this->assertEquals($ModuleInstance, $active_modules[ModuleTester::class]);
        $this->assertTrue($ModuleInstance->initialized);
        $this->assertTrue($ModuleInstance->featureOneRan);
        $this->assertTrue($ModuleInstance->featureTwoRan);
        $this->assertFalse($ModuleInstance->featureThreeRan);
    }

}