<?php

namespace Sitchco\Rewrite;

use Sitchco\Utils\Hooks;

class Route extends Rewrite
{

    protected $callback;

    public function __construct($path, callable $callback)
    {
        parent::__construct($path);
        $this->callback = $callback;
    }

    public function init(): void
    {
        parent::init();
        Hooks::do_eager_action('wp', [$this, 'processRoute']);

    }

    protected function getQuery(): string
    {
        $query = 'route=' . $this->hook;
        for ($index = 1; $index <= $this->arguments_count; $index++) {
            $query .= sprintf('&%s=$matches[%d]', $this->getArgumentName($index), $index);
        }
        return $query;
    }

    public function setQueryVars($query_vars)
    {
        $query_vars[] = 'route';
        for ($index = 1; $index <= $this->arguments_count; $index++) {
            $query_vars[] = $this->getArgumentName($index);
        }
        return $query_vars;
    }

    public function processRoute()
    {
        if(!$this->matchesRequest()) return false;
        $parameters = [];
        for ($index = 1; $index <= $this->arguments_count; $index++) {
            $parameters[] = $this->getArgumentValue($index);
        }
        return call_user_func_array($this->callback, $parameters);
    }

    protected function matchesRequest(): bool
    {
        return get_query_var('route') == $this->hook;
    }

}