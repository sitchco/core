<?php

namespace Sitchco\Tests\Support;

use DI\Container;

abstract class TestCase extends \WPTest\Test\TestCase {

    protected Container $container;

    protected function setUp(): void
    {
        $this->container = $GLOBALS['SitchcoContainer'];
        parent::setUp();
    }
}