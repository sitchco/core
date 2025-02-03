<?php

namespace Sitchco\Repository\Support;

/**
 * interface ImmutableRepository
 * @package Sitchco\Model\Support
 */
interface ImmutableRepository
{
    function findById($id);

    function findOne(array $query);

    function findAll();

    function find(array $query);
}