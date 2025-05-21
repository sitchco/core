<?php

namespace Sitchco\Modules;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;

class BlockManager extends Module
{
    public function __construct(
        private readonly ConfigRegistry $configRegistry
    ) {
    }

    public function init()
    {
        add_filter('gbm_disabled_blocks', [$this, 'filterDisabledBlocks']);
    }

    public function filterDisabledBlocks(): array
    {
        $disallowedBlockList = $this->configRegistry->load('disallowedBlocks');
        return array_keys(array_filter($disallowedBlockList));
    }
}
