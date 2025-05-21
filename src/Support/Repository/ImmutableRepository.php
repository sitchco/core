<?php

namespace Sitchco\Support\Repository;

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
