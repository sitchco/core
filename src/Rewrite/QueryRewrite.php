<?php

declare(strict_types=1);

namespace Sitchco\Rewrite;

/**
 * class QueryRewrite
 * @package Sitchco\Rewrite
 */
class QueryRewrite extends Rewrite
{
    protected array $query;

    public function __construct(string $path, array $query)
    {
        parent::__construct($path);
        $this->query = $query;
    }

    public function setQueryVars(array $queryVars): array
    {
        return [...$queryVars, ...array_keys($this->query)];
    }

    public function getQuery(): string
    {
        return preg_replace('/=\$(\d)/', '=$matches[$1]', build_query($this->query)) ?? '';
    }
}
