<?php

namespace Sitchco\Rest;

use Closure;
use Sitchco\Support\HookName;
use WP_REST_Server;

/**
 * class RestRouteService
 * @package Sitchco\Rest
 */
class RestRouteService
{
    private string $namespace;
    private array $routes = [];

    /**
     * Constructor for RestRouteService.
     *
     * @param string $namespace The API namespace, appended to the root: 'sitchco' by default.
     * @param string|null $root The namespace root. Pass null (or '') to opt out of the 'sitchco'
     *                          prefix entirely, for routes that need to own their own vendor
     *                          namespace — e.g. new RestRouteService('roundabout/v1', root: null).
     */
    public function __construct(string $namespace = '', ?string $root = HookName::ROOT)
    {
        // join() filters out empty parts, so a null/'' root simply drops away and the default
        // stays byte-identical to the Hooks::name() result this replaced.
        $this->namespace = HookName::join((string) $root, $namespace);
    }

    /**
     * Retrieves all registered routes.
     *
     * @return array The list of registered routes.
     */
    public function getRegisteredRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Adds a REST route to the service.
     *
     * @param RestRoute $route The route instance to add.
     */
    public function addRoute(RestRoute $route): void
    {
        $this->routes[] = $route;
        $route->register($this->namespace);
    }

    /**
     * Adds a read (GET) route.
     *
     * @param string $path The route path.
     * @param Closure $callback The function to handle the request.
     * @param string $capability Optional WP capability.
     * @param array $args REST API argument definitions for request validation.
     */
    public function addReadRoute(string $path, Closure $callback, string $capability = '', array $args = []): void
    {
        $this->addRoute(new RestRoute($path, WP_REST_Server::READABLE, $callback, $capability, $args));
    }

    /**
     * Adds a create (POST) route.
     *
     * @param string $path The route path.
     * @param Closure $callback The function to handle the request.
     * @param string $capability Optional WP capability.
     * @param array $args REST API argument definitions for request validation.
     */
    public function addCreateRoute(string $path, Closure $callback, string $capability = '', array $args = []): void
    {
        $this->addRoute(new RestRoute($path, WP_REST_Server::CREATABLE, $callback, $capability, $args));
    }
}
