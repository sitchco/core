<?php

namespace Sitchco\Collection;

use Illuminate\Support\Collection as IlluminateCollection;
use JsonSerializable;
use Timber\Pagination;
use Timber\Post;
use Timber\PostCollectionInterface;
use Timber\Timber;
use WP_Query;

/**
 * Class Collection
 * A shared interface between Timber and Illuminate for collection handling.
 */
class Collection extends IlluminateCollection implements PostCollectionInterface, JsonSerializable
{
    protected ?Pagination $pagination = null;
    protected WP_Query $wp_query;

    public function __construct($items = [])
    {
        if ($items instanceof WP_Query) {
            $this->wp_query = $items;
            $posts = array_map(fn($post) => Timber::get_post($post), $items->posts ?: []);
            parent::__construct($posts);
            $this->pagination = new Pagination([], $this->wp_query);
        } else if (is_array($items) && !$items[0] instanceof Post) {
            throw new \InvalidArgumentException('Items in the collection are not a Timber\Post');
        } else {
            parent::__construct($items);
        }
    }

    public function pagination(array $options = []): ?Pagination
    {
        if (!$this->pagination) {
            $this->pagination = new Pagination($options, $this->wp_query);
        }

        return $this->pagination;
    }

    public function to_array(): array
    {
        return $this->all();
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function jsonSerialize(): array
    {
        return $this->to_array();
    }
}
