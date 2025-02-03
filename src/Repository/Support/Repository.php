<?php

namespace Sitchco\Repository\Support;

/**
 * interface Repository
 * @package Sitchco\Support
 */
interface Repository extends ImmutableRepository
{
    function add($object);
    function remove($object);
}