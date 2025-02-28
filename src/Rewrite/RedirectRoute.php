<?php

namespace Sitchco\Rewrite;

use JetBrains\PhpStorm\NoReturn;

/**
 * class RedirectRoute
 * @package Sitchco\Rewrite
 */
class RedirectRoute extends Route
{
    private string $redirectUrl;

    public function __construct(string $path, \Closure $callback, string $redirectUrl = '/')
    {
        parent::__construct($path, $callback);
        $this->redirectUrl = home_url($redirectUrl);
    }

    #[NoReturn] public function processRoute(): void
    {
        if (untrailingslashit($_SERVER['REQUEST_URI']) === untrailingslashit(parse_url($this->redirectUrl, PHP_URL_PATH))) {
            return;
        }

        parent::processRoute();
        wp_redirect($this->redirectUrl, 302);
        exit;
    }

}