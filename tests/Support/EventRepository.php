<?php

namespace Sitchco\Tests\Support;

use Sitchco\Repository\PostRepository;

/**
 * class EventRepository
 * @package Sitchco\Tests\Support
 */
class EventRepository extends PostRepository
{
    protected string $model_class = EventPost::class;
}