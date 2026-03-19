<?php

namespace Sitchco\Tests\Modules\CustomTags;

use Sitchco\Modules\CustomTags\CustomTags;
use Sitchco\Tests\TestCase;

class CustomTagsTest extends TestCase
{
    public function test_module_is_registered(): void
    {
        $module = $this->container->get(CustomTags::class);
        $this->assertInstanceOf(CustomTags::class, $module);
    }
}
