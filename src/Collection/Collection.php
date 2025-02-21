<?php

namespace Sitchco\Collection;

use Illuminate\Support\Collection as IlluminateCollection;
use JsonSerializable;
use Timber\Pagination;
use Timber\PostCollectionInterface;
use Timber\PostQuery;
use WP_Query;

/**
 * Class Collection
 * A decorator for Timber's PostQuery that provides collection-like functionality while extending IlluminateCollection.
 */
class Collection extends IlluminateCollection implements PostCollectionInterface, JsonSerializable
{
    protected PostQuery $postQuery;

    public function __construct(PostQuery $postQuery)
    {
        $this->postQuery = $postQuery;
        parent::__construct($postQuery); // Initialize IlluminateCollection with the posts
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
