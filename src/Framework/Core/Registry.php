<?php

namespace Sitchco\Framework\Core;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;

/**
 * Class Registry
 * Manages the registration and activation of modules within the framework.
 * @package Sitchco\Framework\Core
 */
class Registry
{
    /**
     * @var array<string> List of registered module class names
     */
    private array $registeredModuleClassnames = [];

    /**
     * @var array<string, Module>
     *     Associative array mapping module name to the activated module instance .
     */
    private array $activeModuleInstances = [];

    protected Container $Container;

    /**
     * @param Container $Container
     */
    public function __construct(Container $Container)
    {
        $this->Container = $Container;
    }

    /**
     * Activates registered modules based on the active module configuration.
     * Retrieves the module class map and the list of active modules.
     * For each active module, it checks if the class exists and instantiates it.
     * If features are specified, it invokes the corresponding methods on the module instance.
     *
     * @param array<string, array<string, bool>|bool> $module_configs The merged list of module configuration.
     *
     * @return array<string, Module> Active module list
     */
    public function activateModules(array $module_configs): array
    {
        $this->addModules(array_keys($module_configs));
        $registeredModulesSorted = $this->getModuleClassmap();

        $activeModules = array_filter(array_map(function ($features) {
            return is_array($features) ? array_filter($features) : $features;
        }, $module_configs));
        foreach ($activeModules as $moduleName => $featureList) {
            $module = $registeredModulesSorted[$moduleName] ?? null;
            if (!class_exists($module)) {
                continue;
            }
            try {
                $instance = $this->Container->get($module); /* @var Module $instance */
                $this->activeModuleInstances[$moduleName] = $instance;
                // default to activate all features
                if (!is_array($featureList) && count($instance::FEATURES)) {
                    $featureList = array_fill_keys($instance::FEATURES, true);
                }
                foreach ((array) $featureList as $feature => $status) {
                    if (method_exists($instance, $feature)) {
                        call_user_func([$instance, $feature]);
                    }
                }
            } catch (DependencyException|NotFoundException) {
            }

        }
        return $this->activeModuleInstances;
    }

    /**
     * Retrieves the full list of registered modules with their features.
     * Iterates through the registered modules and constructs an associative array
     * where the keys are module names and the values are arrays of feature flags or boolean true.
     * @return array<string, array<string, bool>|bool> Full list of modules and their features.
     */
    public function getModuleFeatures(): array
    {
        $list = [];
        foreach ($this->registeredModuleClassnames as $module) {
            $features = (array_fill_keys($module::FEATURES ?: [], true)) ?: true;
            $list[$module] = $features;
        }

        return $list;
    }

    /**
     * Retrieves the list of active modules after applying filters.
     * Applies the 'sitchco/modules/activate' filter to determine which modules should be activated.
     * The filter receives an empty array and the full list of modules as arguments.
     * @return array<string, array<string, bool>|bool> List of active modules and their features.
     */
    public function getActiveModules(): array
    {
        return $this->activeModuleInstances;
    }

    /**
     * Builds and retrieves the module class map after sorting and applying filters.
     * Sorts the modules based on their PRIORITY constant.
     * Constructs an associative array mapping module names to their class names.
     * Applies the 'sitchco/modules/registered' filter to allow modifications to the registered modules.
     * @return array<string, string> Associative array of registered modules.
     */
    protected function getModuleClassmap(): array
    {
        $modules = $this->registeredModuleClassnames;
        //usort($modules, fn($a, $b) => $a::PRIORITY <=> $b::PRIORITY);
        $registeredModules = array_reduce($modules, function ($carry, $module) {
            $carry[$module] = $module;

            return $carry;
        }, []);

        return apply_filters('sitchco/modules/registered', $registeredModules);
    }

    /**
     * Adds modules to the registry.
     * Merges the provided class names with the existing list of module class names.
     *
     * @param array<string>|string $classnames Array or single string of module class names to add.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function addModules(array|string $classnames): static
    {
        $valid_classnames = array_filter((array)$classnames, fn($c) => is_subclass_of($c, Module::class));
        $this->registeredModuleClassnames = array_merge($this->registeredModuleClassnames, $valid_classnames);

        return $this;
    }

}