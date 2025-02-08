<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\Core\Module;

interface ModuleExtension
{
    /**
     * @param Module[] $modules
     * @return void
     */
    public function extend(array $modules): void;
}