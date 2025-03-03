<?php

namespace Sitchco\Rewrite;

use Sitchco\Utils\Hooks;

abstract class Rewrite
{
    protected string $hook;

    protected string $path;

    protected int $arguments_count = 0;

    public function __construct(string $path)
    {
        $this->hook = 'route_' . md5($path);
        $this->path = $path;
        $this->setArgumentCount();
    }

    public function init(): void
    {
        Hooks::add_eager_action('wp_loaded', [$this, 'addRewriteRule']);
        add_filter('query_vars', [$this, 'setQueryVars']);
    }

    public function addRewriteRule(): void
    {
        add_rewrite_rule($this->path, 'index.php?' . $this->getQuery(), 'top');
    }

    public function setQueryVars($query_vars)
    {
        return $query_vars;
    }

    protected function setArgumentCount(): void
    {
        $this->arguments_count = (int) preg_match_all('/\(.*?\)/', $this->path);
    }

    protected function matchesRequest(): bool
    {
        return get_query_var('route') == $this->hook;
    }

    protected function getArgumentName($index): string
    {
        return 'route_arg_' . $index;
    }

    protected function getArgumentValue($index)
    {
        return get_query_var($this->getArgumentName($index));
    }

    abstract protected function getQuery(): string;

}