<?php

namespace Sitchco\Framework\Core;

class Registry
{
    use Singleton;
    private $moduleClassnames = [];
    private $registeredModules = [];

    protected function __construct()
    {
        add_action('after_setup_theme', [$this, 'activateModules']);
    }

    public function activateModules()
    {
        $this->registeredModules = $this->getModuleClassmap();
        $activeModules = array_filter(array_map(function($features){
            return is_array($features) ? array_filter($features) : $features;
        }, $this->getActiveList()));
        foreach ($activeModules as $moduleName => $featureList) {
            $module = $this->registeredModules[$moduleName] ?? null;
            if(class_exists($module)) {
                $instance = method_exists($module, 'getInstance') ? $module::getInstance() : new $module();
                if(is_array($featureList)) {
                    foreach ($featureList as $feature) {
                        if (method_exists($instance, $feature)) {
                            call_user_func([$instance, $feature]);
                        }
                    }
                }
            }
        }
    }

    public function getFullList(): array
    {
        $list = [];
        foreach ($this->registeredModules as $module) {
            $features = (array_fill_keys($module::FEATURES ?: [], true)) ?: true;
            $list[$module::name()] = $features;
        }

        return $list;
    }
    
    protected function getActiveList(): array
    {
        // This integration is handled by JsonConfig but there may be a better way to establish this relationship.
        return apply_filters('sitchco/modules/activate', [], $this->getFullList());
    }

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

    public function addModules(array|string $classnames): static
    {
        $this->moduleClassnames = array_merge($this->moduleClassnames, (array) $classnames);
        return $this;
    }

    /**
     * @param array|string $classnames
     *
     * @return static
     */
    public static function add(array|string $classnames): static
    {
        return static::getInstance()->addModules($classnames);
        
    }
}