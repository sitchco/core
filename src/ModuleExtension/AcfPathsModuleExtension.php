<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Support\AcfJsonSupport;

class AcfPathsModuleExtension implements ModuleExtension
{
    protected AcfJsonSupport $acfJsonSupport;

    public function __construct(AcfJsonSupport $acfJsonSupport)
    {
        $this->acfJsonSupport = $acfJsonSupport;
    }

    public function extend(array $modules): void
    {
        $this->acfJsonSupport->init($modules);
    }
}
