<?php

namespace Sitchco\Framework\Config;

use DI\ContainerBuilder;
use Sitchco\Utils\ArrayUtil;

/**
 * Class ContainerConfigLoader
 * Loads
 * Integrates with the Registry to manage module activation and paths.
 * @package Sitchco\Framework\Config
 */
class ContainerDefinitionConfigLoader extends PhpConfigLoader
{
    protected ContainerBuilder $Builder;

    /**
     * @param ContainerBuilder $Builder
     */
    public function __construct(ContainerBuilder $Builder)
    {
        $this->Builder = $Builder;
    }


    protected function getHookName(): string
    {
        return 'container';
    }

    protected function getConfigFileName(): string
    {
        return 'container.php';
    }

    protected function applyLoadedConfig(array $config): void
    {
        $this->Builder->addDefinitions($config);
    }
}