<?php

namespace Sitchco\Rewrite;

use Sitchco\Utils\Env;

class RedirectRoute extends Route
{
    protected string $redirect_url;

    public function __construct($path, callable $callback, $redirect_url = '/')
    {
        parent::__construct($path, $callback);
        $this->redirect_url = home_url($redirect_url);
    }

    public function processRoute()
    {
        $redirect = parent::processRoute();
        if (false !== $redirect) {
            Env::redirectExit($this->redirect_url);
        }
        return $redirect;
    }
}
