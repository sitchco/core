<?php

namespace Sitchco\Collection;

use Illuminate\Support\Collection as IlluminateCollection;
use JsonSerializable;
use Timber\Pagination;
use Timber\PostCollectionInterface;
use Timber\PostQuery;

/**
 * Class Collection
 * A decorator for Timber's PostQuery that provides collection-like functionality while extending IlluminateCollection.
 */
class Collection extends IlluminateCollection implements PostCollectionInterface, JsonSerializable
{
    protected PostQuery $postQuery;
    protected ?Pagination $pagination = null;

    public function __construct(PostQuery $postQuery)
    {
        $this->postQuery = $postQuery;
        parent::__construct($postQuery->to_array()); // Initialize IlluminateCollection with the posts
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
        return $this->to_array();
    }

    /**
     * Delegate method calls to PostQuery if not found in IlluminateCollection
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->postQuery, $method)) {
            return $this->postQuery->{$method}(...$parameters);
        }

        return parent::__call($method, $parameters);
    }
}
