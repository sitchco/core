<?php

namespace Sitchco\Tests\ModuleExtension;

use Sitchco\Tests\TestCase;
use WP_Block_Type_Registry;

class BlockRegistrationModuleExtensionTest extends TestCase
{
    public function test_module_blocks_are_registered(): void
    {
        // Verify the test block from ModuleTester was registered via the manifest system
        $this->assertTrue(
            WP_Block_Type_Registry::get_instance()->is_registered('sitchco/test-block'),
            'Blocks from module should be auto-registered via manifest',
        );
    }
}
