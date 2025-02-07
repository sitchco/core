<?php

namespace Sitchco\Tests\Support;

use Sitchco\Framework\Core\Module;

class ModuleTester extends Module
{
    const FEATURES = [
        'featureOne',
        'featureTwo',
        'featureThree',
    ];

    public bool $initialized = false;

    public bool $featureOneRan = false;
    public bool $featureTwoRan = false;
    public bool $featureThreeRan = false;

    public function init() {
        $this->initialized = true;
    }

    public function featureOne(): void
    {
        $this->featureOneRan = true;
    }
    public function featureTwo(): void
    {
        $this->featureTwoRan = true;
    }

    public function featureThree(): void
    {
        $this->featureThreeRan = true;
    }
}