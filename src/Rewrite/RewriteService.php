<?php

namespace Sitchco\Rewrite;

use Closure;
use InvalidArgumentException;

/**
 * class RewriteService
 * @package Sitchco\Rewrite
 */
class RewriteService
{
    protected array $rewriteRules = [];

    public function getRegisteredRewriteRules(): array
    {
        return $this->rewriteRules;
    }

    /**
     * Register a standard rewrite rule or a route.
     *
     * @param string $path
     * @param array $args {
     * *  @type array $query array of WP_Query parameters
     * *  @type callable $callback function to execute when URL matches the rewrite path
     * *  @type string $redirect_url URL to redirect to if callback function returns true
     * }
 * @return RewriteService
     */
    public function register(string $path, array $args): RewriteService
    {
        $args = wp_parse_args($args, [
            'query' => [],
            'callback' => null,
            'redirect_url' => '',
        ]);
        if (is_callable($args['callback'])) {
            // Determine if it's a redirect route
            $route = $args['redirect_url'] ? new RedirectRoute($path, $args['callback'], (string) $args['redirect_url']) : new Route($path, $args['callback']);
        } else {
            // Default to QueryRewrite
            $route = new QueryRewrite($path, (array) $args['query']);
        }

        $this->rewriteRules[] = $route;
        $route->init();
        return $this;
    }
}
