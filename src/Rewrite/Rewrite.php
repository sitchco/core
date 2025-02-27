<?php

declare(strict_types=1);

namespace Sitchco\Rewrite;

/**
 * class Rewrite
 * @package Sitchco\Rewrite
 */
abstract class Rewrite
{
    protected string $hook;
    protected string $path;
    protected int $arguments_count = 0;
    protected mixed $callback = null;

    public function __construct(string $path)
    {
        $this->hook = 'route_' . md5($path);
        $this->path = $path;
        $this->setArgumentCount();
    }

    public function addRewriteRule(): void
    {
        add_rewrite_rule($this->path, 'index.php?' . $this->getQuery(), 'top');
    }

    public function setQueryVars(array $query_vars): array
    {
        return $query_vars;
    }

    public function getArgumentName(int $index): string
    {
        return "route_arg_{$index}";
    }

    protected function setArgumentCount(): void
    {
        $this->arguments_count = preg_match_all('/\(.*?\)/', $this->path) ?: 0;
    }

    protected function matchesRequest(): bool
    {
        return get_query_var('route') === $this->hook;
    }

    protected function getArgumentValue(int $index): ?string
    {
        return get_query_var($this->getArgumentName($index)) ?: null;
    }

    abstract public function getQuery(): string;
}