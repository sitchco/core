<?php

namespace Sitchco\Tests;

use DI\DependencyException;
use DI\NotFoundException;
use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Tests\Fakes\ModuleTester\ModuleTester;
use Sitchco\Tests\Fakes\ParentModuleTester;
use Sitchco\Tests\Fakes\PostTester;

class CoreMuPluginTest extends TestCase
{
    function test_registers_and_activates_a_module()
    {
        $loaded_config = $this->container->get(ConfigRegistry::class)->load('modules');
        $this->assertEquals(
            [
                'featureOne' => true,
                'featureTwo' => true,
                'featureThree' => false,
            ],
            $loaded_config[ModuleTester::class],
        );
        $this->assertArrayNotHasKey(ParentModuleTester::class, $loaded_config);
        $full_feature_list = $this->container->get(ModuleRegistry::class)->getModuleFeatures();
        $this->assertEquals(['featureOne', 'featureTwo', 'featureThree'], $full_feature_list[ModuleTester::class]);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    function test_active_module_initialization()
    {
        $active_modules = $this->container->get(ModuleRegistry::class)->getActiveModules();
        $ModuleInstance = $this->container->get(ModuleTester::class);
        $this->assertEquals($ModuleInstance, $active_modules[ModuleTester::class]);
        $this->assertArrayHasKey(ParentModuleTester::class, $active_modules);
        $this->assertTrue($ModuleInstance->initialized);
        $this->assertTrue($ModuleInstance->featureOneRan);
        $this->assertTrue($ModuleInstance->featureTwoRan);
        $this->assertFalse($ModuleInstance->featureThreeRan);
    }

    function test_active_module_post_classes()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post',
        ]);
        $post = \Timber\Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);
    }

    function test_active_module_path()
    {
        $ModuleInstance = $this->container->get(ModuleTester::class);
        $this->assertEquals(
            WP_CONTENT_DIR . '/mu-plugins/sitchco-core/tests/Fakes/ModuleTester/',
            $ModuleInstance->path()->value(),
        );
    }
}
