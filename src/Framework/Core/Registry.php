<?php

namespace Sitchco\Framework\Core;

class Registry
{
    use Singleton;
    private static $moduleClassnames = [];
    private $registeredModules = [];

    protected function __construct()
    {
        add_action('after_setup_theme', [$this, 'activateModules']);
    }

    public function activateModules()
    {
        $this->registeredModules = $this->getModuleClassmap();
        $activeModules = $this->getActiveList();
        foreach ($activeModules as $moduleName => $featureList) {
            $module = $this->registeredModules[$moduleName] ?? null;
            if ($module && method_exists($module, 'init')) {
                $instance = $module::init();
                foreach ($featureList as $feature) {
                    if (method_exists($instance, $feature)) {
                        call_user_func([$instance, $feature]);
                    }
                }
            }
        }
    }

    public function getFullList(): array
    {
        $list = [];
        foreach ($this->registeredModules as $module) {
            $list[$module::name()] = array_keys($module::FEATURES ?? []) ?: true;
        }

        return $list;
    }

    protected function getDefaultList(): array
    {
        $list = [];
        foreach ($this->registeredModules as $module) {
            if ($module::DEFAULT) {
                $list[$module::name()] = array_keys(array_filter($module::FEATURES ?? [])) ?: true;
            }
        }

        return $list;
    }

    protected function getActiveList(): array
    {
        // This integration is handled by JsonConfig but there may be a better way to establish this relationship.
        return apply_filters('sitchco/modules/active', $this->getDefaultList());
    }

    protected function getModuleClassmap(): array
    {
        $modules = static::$moduleClassnames;
        usort($modules, fn($a, $b) => $a::PRIORITY <=> $b::PRIORITY);
        $registeredModules = array_reduce($modules, function ($carry, $module) {
            $carry[$module::name()] = $module;

            return $carry;
        }, []);

        return apply_filters('sitchco/modules/registered', $registeredModules);
    }

    /**
     * @param array|string $classnames
     *
     * @return void
     */
    public static function add(array|string $classnames): void
    {
        static::$moduleClassnames = array_merge(static::$moduleClassnames, (array) $classnames);
    }
}