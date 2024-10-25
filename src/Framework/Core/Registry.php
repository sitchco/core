<?php

namespace Sitchco\Framework\Core;

class Registry
{
    private static $modules = [];
    private $registeredModules = [];

    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'activateModules']);
    }

    public function activateModules()
    {
        $modules = static::$modules;
        usort($modules, fn($a, $b) => $a::PRIORITY <=> $b::PRIORITY);
        $registeredModules = array_reduce($modules, function ($carry, $module) {
            $carry[$module::NAME ?? $module] = $module;

            return $carry;
        }, []);
        $this->registeredModules = apply_filters('sitchco/modules/registered', $registeredModules);
        $activeModules = apply_filters('sitchco/modules/active', $this->getDefaultList());
        foreach ($activeModules as $moduleName => $featureList) {
            $module = $this->registeredModules[$moduleName];
            $instance = new $module();
            foreach ($featureList as $feature) {
                if (method_exists($instance, $feature)) {
                    call_user_func([$instance, $feature]);
                }
            }
        }
    }

    public function getFullList(): array
    {
        $list = [];
        foreach ($this->registeredModules as $module) {
            $list[$module::NAME] = array_keys($module::FEATURES ?? []);
        }

        return $list;
    }

    protected function getDefaultList(): array
    {
        $list = [];
        foreach ($this->registeredModules as $module) {
            if ($module::ENABLED) {
                $list[$module::NAME] = array_keys(array_filter($module::FEATURES ?? []));
            }
        }

        return $list;
    }

    public static function add($classname): void
    {
        static::$modules[] = $classname;
    }
}