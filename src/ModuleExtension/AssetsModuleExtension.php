<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class AssetsModuleExtension implements ModuleExtension
{
    /**
     * @var Module[]
     */
    protected array $modules;

    /**
     * @var array
     */
    protected array $moduleAssets;

    /**
     * @param Module[] $modules
     * @return void
     */
    public function extend(array $modules): void
    {
        $this->modules = $modules;
        foreach ($this->modules as $module) {
            $this->moduleAssets[$module::class] = new ModuleAssets($module->path());
        }
        add_action('wp_enqueue_scripts', $this->buildMethodCallable('enqueueFrontendAssets'));
        add_action('enqueue_block_assets', $this->buildMethodCallable( 'enqueueGlobalAssets'));
        add_action('enqueue_block_editor_assets', $this->buildMethodCallable( 'enqueueEditorAssets'));
        add_action('init', $this->buildMethodCallable( 'registerAssets'), 20);
        add_action('init', $this->buildMethodCallable( 'enqueueBlockStyles'), 30);
    }

    public function buildMethodCallable(string $methodName): callable
    {
        return function() use ($methodName) {
            foreach ($this->modules as $module) {
                if (method_exists($module, $methodName)) {
                    $assets = $this->getModuleAssets($module);
                    $module->$methodName($assets);
                }
            }
        };
    }

    public function getModuleAssets(Module $module): ModuleAssets
    {
        if (!isset($this->moduleAssets[$module::class])) {
            $this->moduleAssets[$module::class] = new ModuleAssets($module->path());
        }
        return $this->moduleAssets[$module::class];
    }
}
