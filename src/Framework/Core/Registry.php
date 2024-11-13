<?php

namespace Sitchco\Framework\Core;

/**
 * Class Registry
 * Manages the registration and activation of modules within the framework.
 * Implements the Singleton pattern to ensure a single instance throughout the application.
 * @package Sitchco\Framework\Core
 */
class Registry
{
    use Singleton;

    /**
     * @var array<string> List of module class names to be registered.
     */
    private array $moduleClassnames = [];

    /**
     * @var array<string, string> Associative array mapping module names to their class names.
     */
    private array $registeredModules = [];

    /**
     * Registry constructor.
     * Initializes the Registry by hooking the activateModules method to the 'after_setup_theme' action.
     */
    protected function __construct()
    {
        add_action('after_setup_theme', [$this, 'activateModules']);
    }

    /**
     * Activates registered modules based on the active module list.
     * Retrieves the module class map and the list of active modules.
     * For each active module, it checks if the class exists and instantiates it.
     * If features are specified, it invokes the corresponding methods on the module instance.
     * @return void
     */
    public function activateModules()
    {
        $this->registeredModules = $this->getModuleClassmap();
        $activeModules = array_filter(array_map(function ($features) {
            return is_array($features) ? array_filter($features) : $features;
        }, $this->getActiveList()));
        foreach ($activeModules as $moduleName => $featureList) {
            $module = $this->registeredModules[$moduleName] ?? null;
            if (class_exists($module)) {
                $instance = method_exists($module, 'init') ? $module::init() : new $module();
                if (is_array($featureList)) {
                    foreach ($featureList as $feature => $status) {
                        if (method_exists($instance, $feature)) {
                            call_user_func([$instance, $feature]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Retrieves the full list of registered modules with their features.
     * Iterates through the registered modules and constructs an associative array
     * where the keys are module names and the values are arrays of feature flags or boolean true.
     * @return array<string, array<string, bool>|bool> Full list of modules and their features.
     */
    public function getFullList(): array
    {
        $list = [];
        foreach ($this->registeredModules as $module) {
            $features = (array_fill_keys($module::FEATURES ?: [], true)) ?: true;
            $list[$module::name()] = $features;
        }

        return $list;
    }

    /**
     * Retrieves the list of active modules after applying filters.
     * Applies the 'sitchco/modules/activate' filter to determine which modules should be activated.
     * The filter receives an empty array and the full list of modules as arguments.
     * @return array<string, array<string, bool>|bool> List of active modules and their features.
     */
    protected function getActiveList(): array
    {
        return apply_filters('sitchco/modules/activate', [], $this->getFullList());
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
        $modules = $this->moduleClassnames;
        usort($modules, fn($a, $b) => $a::PRIORITY <=> $b::PRIORITY);
        $registeredModules = array_reduce($modules, function ($carry, $module) {
            $carry[$module::name()] = $module;

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
        $this->moduleClassnames = array_merge($this->moduleClassnames, (array)$classnames);

        return $this;
    }

    /**
     * Static method to add modules to the registry.
     * Retrieves the singleton instance and adds the provided class names to it.
     *
     * @param array<string>|string $classnames Array or single string of module class names to add.
     *
     * @return static Returns the singleton instance for method chaining.
     */
    public static function add(array|string $classnames): static
    {
        return static::getInstance()->addModules($classnames);
    }
}