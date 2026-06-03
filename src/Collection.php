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
    protected ?PostQuery $postQuery = null;

    /**
     * Accepts a PostQuery on initial creation to retain query context (pagination, etc.).
     * Parent collection methods (map, filter, ...) clone via `new static($items)` with plain
     * arrays, so the constructor must also accept any iterable; derived collections simply
     * lose the PostQuery context.
     */
    public function __construct($items = [])
    {
        if ($items instanceof PostQuery) {
            $this->postQuery = $items;
        }
        parent::__construct($items);
    }

    public function pagination(array $options = []): ?Pagination
    {
        return $this->postQuery?->pagination($options);
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
        return $this->postQuery?->query();
    }

    public function realize(): PostQuery|PostCollectionInterface
    {
        return $this->postQuery?->realize() ?? $this;
    }

    public function __debugInfo(): array
    {
        return $this->postQuery?->__debugInfo() ?? $this->items;
    }
}
