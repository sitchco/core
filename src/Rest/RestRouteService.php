<?php

namespace Sitchco\Rest;

use Closure;
use Sitchco\Utils\Hooks;
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
     * @param string $namespace The API namespace, defaults to 'sitchco/v1'.
     */
    public function __construct(string $namespace = '')
    {
        $this->namespace = Hooks::name($namespace);
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
     */
    public function addReadRoute(string $path, Closure $callback, string $capability = ''): void
    {
        $this->addRoute(new RestRoute($path, WP_REST_Server::READABLE, $callback, $capability));
    }

    /**
     * Adds a create (POST) route.
     *
     * @param string $path The route path.
     * @param Closure $callback The function to handle the request.
     * @param string $capability Optional WP capability.
     */
    public function addCreateRoute(string $path, Closure $callback, string $capability = ''): void
    {
        $this->addRoute(new RestRoute($path, WP_REST_Server::CREATABLE, $callback, $capability));
    }
}
