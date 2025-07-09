<?php

namespace Sitchco;

use Illuminate\Support\Collection as IlluminateCollection;
use JsonSerializable;
use Timber\Pagination;
use Timber\PostCollectionInterface;
use Timber\PostQuery;
use WP_Query;

/**
 * Class Collection
 * A decorator for Timber's PostQuery that provides collection-like functionality while extending IlluminateCollection.
 * @package Sitchco
 */
class Collection extends IlluminateCollection implements PostCollectionInterface, JsonSerializable
{
    protected PostQuery $postQuery;

    public function __construct(iterable $postQuery)
    {
        if (! $postQuery instanceof PostQuery) {
            throw new \InvalidArgumentException('Collection iterable must be in instance of PostQuery');
        }
        $this->postQuery = $postQuery;
        parent::__construct($postQuery);
    }

    public function pagination(array $options = []): ?Pagination
    {
        return $this->postQuery->pagination($options);
    }

    public function to_array(): array
    {
        return $this->all();
    }

    public function jsonSerialize(): array
    {
        return $this->all();
    }

    public function query(): ?WP_Query
    {
        return $this->postQuery->query();
    }

    public function realize(): PostQuery|PostCollectionInterface
    {
        return $this->postQuery->realize();
    }

    public function __debugInfo(): array
    {
        return $this->postQuery->__debugInfo();
    }
}
