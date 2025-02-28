<?php

declare(strict_types=1);

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

    /**
     * Register a standard rewrite rule or a route.
     */
    public function register(string $path, array $query = [], ?Closure $callback = null, ?string $redirectUrl = null): RewriteService
    {
        if ($callback) {
            // Determine if it's a redirect route
            $route = $redirectUrl ? new RedirectRoute($path, $callback, $redirectUrl) : new Route($path, $callback);
        } else {
            // Default to QueryRewrite
            $route = new QueryRewrite($path, $query);
        }

        $this->rewriteRules[] = $route;
        return $this;
    }

    /**
     * Register multiple rewrite rules.
     */
    public function registerAll(array $rules): RewriteService
    {
        foreach ($rules as $rule) {
            if (!isset($rule['path']) || empty($rule['path'])) {
                throw new InvalidArgumentException("Each rule must have a 'path' key.");
            }

            $query = $rule['query'] ?? [];
            $callback = $rule['callback'] ?? null;
            $redirectUrl = $rule['redirect'] ?? null;

            $this->register($rule['path'], $query, $callback, $redirectUrl);
        }
        return $this;
    }

    /**
     * Execute all registered rewrite rules and routes.
     */
    public function execute(): RewriteService
    {
        add_action('init', function () {
            foreach ($this->rewriteRules as $rule) {
                $rule->addRewriteRule();
            }
        });

        add_filter('query_vars', function ($query_vars) {
            foreach ($this->rewriteRules as $rule) {
                $query_vars = $rule->setQueryVars($query_vars);
            }
            return $query_vars;
        });

        // Ensure registered routes process correctly on request
        add_action('wp', function () {
            foreach ($this->rewriteRules as $rule) {
                if ($rule instanceof Route) {
                    $rule->processRoute();
                }
            }
        }, 999);

        flush_rewrite_rules();
        return $this;
    }
}
