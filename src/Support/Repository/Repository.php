<?php

namespace Sitchco\Support\Repository;

/**
 * interface Repository
 * @package Sitchco\Support
 */
interface Repository extends ImmutableRepository
{
    function add($object);
    function remove($object);
}
