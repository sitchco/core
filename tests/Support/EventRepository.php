<?php

namespace Sitchco\Tests\Support;

use Sitchco\Repository\RepositoryBase;

/**
 * class EventRepository
 * @package Sitchco\Tests\Support
 */
class EventRepository extends RepositoryBase
{
    protected string $model_class = EventPost::class;
}