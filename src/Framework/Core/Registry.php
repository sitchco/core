<?php

namespace Sitchco\Framework\Core;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Sitchco\ModuleExtension\AcfPathsModuleExtension;
use Sitchco\ModuleExtension\TimberPostModuleExtension;
use Sitchco\Utils\ArrayUtil;

/**
 * Class Registry
 * Manages the registration and activation of modules within the framework.
 * @package Sitchco\Framework\Core
 */
class Registry
{
    /**
     * @var array<string> List of registered module class names.
     */
    private array $registeredModuleClassnames = [];

    /**
     * @var array<string, Module> Associative array mapping module name to the activated module instance.
     */
    private array $activeModuleInstances = [];

    /**
     * @var array<string> Tracks modules in the process of being registered to detect circular dependencies.
     */
    private array $inProgress = [];

    protected Container $Container;

    const EXTENSIONS = [
        TimberPostModuleExtension::class,
        AcfPathsModuleExtension::class,
    ];

    /**
     * @param Container $Container
     */
    public function __construct(Container $Container)
    {
        $this->Container = $Container;
    }

    /**
     * First Pass – Module Registration.
     * Processes dependencies recursively and instantiates the module.
     *
     * @param string $module Fully qualified module class name.
     */
    protected function registerActiveModule(string $module): void
    {
        if (! class_exists($module)) {
            return;
        }

        // Skip if the module has already been instantiated.
        if (isset($this->activeModuleInstances[$module])) {
            return;
        }

        // Detect circular dependency.
        if (in_array($module, $this->inProgress, true)) {
            // Optionally log a warning, e.g.:
            // error_log("Circular dependency detected for module: {$module}");
            return;
        }

        // Mark this module as in progress.
        $this->inProgress[] = $module;

        try {
            // Process dependencies recursively.
            foreach ($module::DEPENDENCIES as $dependency) {
                $this->registerActiveModule($dependency);
            }

            $instance = $this->Container->get($module);
            /* @var Module $instance */
            $this->activeModuleInstances[$module] = $instance;
        } catch (DependencyException|NotFoundException $e) {
            // Optionally log the error, e.g.:
            // error_log("Failed to instantiate module {$module}: " . $e->getMessage());
        }
        finally {
            // Remove the module from the in-progress stack.
            array_pop($this->inProgress);
        }
    }

    /**
     * Activates registered modules based on the active module configuration.
     * The activation process is divided into three passes:
     *   1. Registration Pass: Instantiate and register all active modules.
     *   2. Extension Pass: Process module extensions.
     *   3. Initialization Pass: Initialize each module and call its feature methods.
     *
     * @param array<string, array<string, bool>|bool> $module_configs The merged list of module configurations.
     *
     * @return array<string, Module> Active module instances.
     */
    public function activateModules(array $module_configs): array
    {
        // Add all module classes to the registry.
        $this->addModules(array_keys($module_configs));

        // Execute the three activation passes.
        $this->registrationPass($module_configs);
        $this->extensionPass();
        $this->initializationPass($module_configs);

        return $this->activeModuleInstances;
    }

    /**
     * Registration Pass: Prepares the active modules configuration and registers each module.
     *
     * @param array<string, array<string, bool>|bool> $module_configs
     *
     * @return void
     */
    private function registrationPass(array $module_configs): void
    {
        // Prepare active modules configuration.
        // For each module config, if it's an array, filter out any falsy feature flags.
        $activeModules = array_filter(
            array_map(
                fn($features) => is_array($features) ? array_filter($features) : $features,
                $module_configs
            )
        );

        // Register each active module.
        foreach (array_keys($activeModules) as $module) {
            $this->registerActiveModule($module);
        }
    }

    /**
     * Extension Pass: Apply module extensions.
     * @return void
     */
    private function extensionPass(): void
    {
        foreach (static::EXTENSIONS as $extension_classname) {
            $extension = $this->Container->get($extension_classname);
            $extension->extend(array_values($this->activeModuleInstances));
        }
    }

    /**
     * Initialization Pass: Initialize modules and execute their configured features.
     *
     * @param array<string, array<string, bool>|bool> $module_configs
     *
     * @return void
     */
    private function initializationPass(array $module_configs): void
    {
        foreach ($this->activeModuleInstances as $moduleName => $instance) {
            // Call the module's init method.
            $instance->init();

            // Prepare the feature list for the module.
            $featureList = $module_configs[$moduleName] ?? [];
            if (! is_array($featureList) && count($instance::FEATURES)) {
                $featureList = array_fill_keys($instance::FEATURES, true);
            }

            // Execute each feature method if it exists.
            foreach ((array)$featureList as $feature => $status) {
                if (method_exists($instance, $feature)) {
                    call_user_func([$instance, $feature]);
                }
            }
        }
    }

    /**
     * Retrieves the full list of registered modules with their features.
     * @return array<string, array<string, bool>|bool> Full list of modules and their features.
     */
    public function getModuleFeatures(): array
    {
        $list = [];
        foreach ($this->registeredModuleClassnames as $module) {
            $features = $module::FEATURES ?: [];
            $list[$module] = $features;
        }

        return $list;
    }

    /**
     * Retrieves the list of active modules after activation.
     * @return array<string, Module> Active module instances.
     */
    public function getActiveModules(): array
    {
        return $this->activeModuleInstances;
    }

    /**
     * Adds modules to the registry.
     *
     * @param array<string>|string $classnames Array or single string of module class names to add.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function addModules(array|string $classnames): static
    {
        $valid_classnames = array_filter((array)$classnames, fn($c) => is_subclass_of($c, Module::class));
        $dependency_classnames = ArrayUtil::arrayMapFlat(fn($c) => $c::DEPENDENCIES, $valid_classnames);

        if (count($dependency_classnames)) {
            $this->addModules($dependency_classnames);
        }

        // Merge the new module classnames and remove duplicates.
        $this->registeredModuleClassnames = array_unique(
            array_merge($this->registeredModuleClassnames, $valid_classnames)
        );

        return $this;
    }
}