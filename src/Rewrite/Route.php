<?php

namespace Sitchco\Rewrite;

use Closure;

/**
 * class Route
 * @package Sitchco\Rewrite
 */
class Route extends Rewrite
{
    protected mixed $callback;

    public function __construct(string $path, Closure $callback)
    {
        parent::__construct($path);
        $this->callback = $callback;
    }

    public function addRewriteRule(): void
    {
        parent::addRewriteRule();

        // Hook into WordPress request lifecycle
        add_action('wp', [$this, 'processRoute'], 999);
    }

    public function getQuery(): string
    {
        $query = "route={$this->hook}";
        for ($index = 1; $index <= $this->argumentsCount; $index++) {
            $query .= sprintf('&%s=$matches[%d]', $this->getArgumentName($index), $index);
        }
        return $query;
    }

    public function setQueryVars(array $queryVars): array
    {
        $queryVars[] = 'route';
        for ($index = 1; $index <= $this->argumentsCount; $index++) {
            $queryVars[] = $this->getArgumentName($index);
        }
        return $queryVars;
    }

    public function processRoute(): void
    {
        if (!$this->matchesRequest()) {
            return;
        }

        $parameters = array_map(fn($index) => $this->getArgumentValue($index), range(1, $this->argumentsCount));
        call_user_func_array($this->callback, $parameters);
    }
}
