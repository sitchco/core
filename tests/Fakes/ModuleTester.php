<?php

namespace Sitchco\Tests\Fakes;

use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;

class ModuleTester extends Module
{
    const DEPENDENCIES = [ParentModuleTester::class];

    public const POST_CLASSES = [PostTester::class];

    const FEATURES = ['featureOne', 'featureTwo', 'featureThree'];

    public bool $initialized = false;

    public bool $featureOneRan = false;
    public bool $featureTwoRan = false;
    public bool $featureThreeRan = false;

    public function init(): void
    {
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

    /**
     * Override to force assets to fixture directory
     *
     * @param string $relative
     * @return FilePath
     */
    public function assetsPath(string $relative = ''): FilePath
    {
        return FilePath::create(SITCHCO_CORE_FIXTURES_DIR);
    }
}
