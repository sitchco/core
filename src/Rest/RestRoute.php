<?php

namespace Sitchco\Rest;

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
    private mixed $permissionCallback;

    /**
     * Constructor to initialize the route.
     *
     * @param string $path The route path.
     * @param string|array $methods Allowed HTTP methods.
     * @param callable $callback Function to handle the request.
     * @param callable|null $permissionCallback Function to check permissions.
     */
    public function __construct(string $path, string|array $methods, callable $callback, ?callable $permissionCallback = null)
    {
        $this->path = $path;
        $this->methods = $methods;
        $this->callback = $callback;
        $this->permissionCallback = $permissionCallback;
    }

    /**
     * Registers the REST route with WordPress.
     *
     * @param string $namespace The API namespace.
     */
    public function register(string $namespace): void
    {
        add_action('rest_api_init', function() use ($namespace) {
            register_rest_route($namespace, $this->path, [
                'methods'             => $this->methods,
                'callback'            => [$this, 'handleRequest'],
                'permission_callback' => $this->permissionCallback ?? '__return_true',
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
            return new WP_Error(
                'rest_error',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }
}
