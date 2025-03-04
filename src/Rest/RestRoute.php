<?php

namespace Sitchco\Rest;

use Sitchco\Utils\Hooks;
use WP_Error;
use WP_REST_Request;
use Throwable;

/**
 * class RestRoute
 * @package Sitchco\Rest
 */
class RestRoute
{
    private string $path;
    private string|array $methods;
    private mixed $callback;
    private string $capability;

    /**
     * Constructor to initialize the route.
     *
     * @param string $path The route path.
     * @param string|array $methods Allowed HTTP methods.
     * @param callable $callback Function to handle the request.
     * @param string $capability
     */
    public function __construct(string $path, string|array $methods, callable $callback, string $capability = '')
    {
        $this->path = $path;
        $this->methods = $methods;
        $this->callback = $callback;
        $this->capability = $capability;
    }

    /**
     * Registers the REST route with WordPress.
     *
     * @param string $namespace The API namespace.
     */
    public function register(string $namespace): void
    {
        Hooks::callOrAddAction('rest_api_init', function() use ($namespace) {
            register_rest_route($namespace, $this->path, [
                'methods'             => $this->methods,
                'callback'            => [$this, 'handleRequest'],
                'permission_callback' => $this->permissionCallback(),
            ]);
        });
    }

    /**
     * Handles incoming REST API requests and ensures proper error handling.
     *
     * @param WP_REST_Request $request The incoming request object.
     * @return \WP_REST_Response|WP_Error The response or error.
     */
    public function handleRequest(WP_REST_Request $request): \WP_REST_Response|WP_Error
    {
        try {
            return rest_ensure_response(($this->callback)($request));
        } catch (Throwable $e) {
            $route_key = explode('/', $this->path)[0] ?? 'error';
            return new WP_Error(
                "rest_$route_key",
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }

    protected function permissionCallback(): \Closure
    {
        return function() {
            if (!$this->capability) {
                return true;
            }
            return current_user_can($this->capability) ?:
                new WP_Error(
                    'incorrect_permissions',
                    'Incorrect permissions for requested route',
                    ['status' => rest_authorization_required_code()]
                );
        };
    }
}
