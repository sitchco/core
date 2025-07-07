<?php

namespace Sitchco\Tests\Support;

use Sitchco\Repository\RepositoryBase;

/**
 * class EventRepositoryTester
 * @package Sitchco\Tests\Support
 */
class EventRepositoryTester extends RepositoryBase
{
    protected string $model_class = EventPostTester::class;
}
