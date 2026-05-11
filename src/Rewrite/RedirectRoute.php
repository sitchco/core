<?php

namespace Sitchco\Rewrite;

use Sitchco\Utils\Env;

class RedirectRoute extends Route
{
    protected ?string $redirect_url;

    public function __construct($path, callable $callback, ?string $redirect_url = null)
    {
        parent::__construct($path, $callback);
        $this->redirect_url = $redirect_url !== null ? home_url($redirect_url) : null;
    }

    public function processRoute()
    {
        $redirect = parent::processRoute();
        if (false === $redirect) {
            return false;
        }
        $url = is_string($redirect) ? $redirect : $this->redirect_url;
        if ($url) {
            Env::redirectExit($url);
        }
        return $redirect;
    }
}
