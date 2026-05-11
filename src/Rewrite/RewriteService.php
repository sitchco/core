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
     * *  @type string|bool $redirect_url URL to redirect to if callback returns truthy.
     * *      Pass `true` to use the callback's string return value as the redirect URL.
     * }
     * @return RewriteService
     */
    public function register(string $path, array $args): RewriteService
    {
        $args = wp_parse_args($args, [
            'query' => [],
            'callback' => null,
            'redirect_url' => null,
        ]);
        if (is_callable($args['callback'])) {
            if ($args['redirect_url'] === null || $args['redirect_url'] === false) {
                $route = new Route($path, $args['callback']);
            } else {
                $static_url = is_string($args['redirect_url']) ? $args['redirect_url'] : null;
                $route = new RedirectRoute($path, $args['callback'], $static_url);
            }
        } else {
            // Default to QueryRewrite
            $route = new QueryRewrite($path, (array) $args['query']);
        }

        $this->rewriteRules[] = $route;
        $route->init();
        return $this;
    }
}
