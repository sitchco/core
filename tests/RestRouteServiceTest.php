<?php

namespace Sitchco\Tests;

use Sitchco\Rest\RestRoute;
use Sitchco\Rest\RestRouteService;
use WP_REST_Request;

/**
 * class RestRouteServiceTest
 * @package Sitchco\Tests
 */
class RestRouteServiceTest extends TestCase
{
    private RestRouteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(RestRouteService::class);
        $GLOBALS['wp_rest_server'] = null;
    }

    /**
     * Test that a read route can be added and handled correctly.
     */
    public function testAddReadRoute(): void
    {
        $data = ['status' => 'ok'];
        $this->service->addReadRoute('/example-read', fn() => $data);

        $registered = $this->service->getRegisteredRoutes();
        $route = end($registered);
        $this->assertInstanceOf(RestRoute::class, $route);
        $routes = rest_get_server()->get_routes('sitchco');
        $this->assertEquals(
            [
                'methods' => ['GET' => true],
                'accept_json' => false,
                'accept_raw' => false,
                'show_in_index' => true,
                'args' => [],
                'callback' => [$route, 'handleRequest'],
                'permission_callback' => fn() => true,
            ],
            $routes['/sitchco/example-read'][0],
        );
        // Simulate a GET request to the registered route
        $request = new WP_REST_Request('GET', '/sitchco/example-read');
        $response = rest_do_request($request);

        // Assertions to check the response
        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($data, $response->get_data());
    }

    /**
     * Test that a create route can be added and handled correctly.
     */
    public function testAddCreateRoute(): void
    {
        $data = ['status' => 'ok'];
        $this->service->addCreateRoute('/example-create', fn() => $data);

        $registered = $this->service->getRegisteredRoutes();
        $route = end($registered);
        $this->assertInstanceOf(RestRoute::class, $route);

        $routes = rest_get_server()->get_routes('sitchco');
        $this->assertEquals(
            [
                'methods' => ['POST' => true],
                'accept_json' => false,
                'accept_raw' => false,
                'show_in_index' => true,
                'args' => [],
                'callback' => [$route, 'handleRequest'],
                'permission_callback' => fn() => true,
            ],
            $routes['/sitchco/example-create'][0],
        );

        // Simulate a POST request to the registered route
        $request = new WP_REST_Request('POST', '/sitchco/example-create');
        $response = rest_do_request($request);

        // Assertions to check the response
        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($data, $response->get_data());
    }

    /**
     * Test that routes can be registered with a custom namespace.
     */
    public function testNamespaceCustomization(): void
    {
        $data = ['status' => 'ok'];
        $customNamespace = 'custom';
        $service = new RestRouteService($customNamespace);
        $service->addReadRoute('/custom-namespace', fn() => $data);

        $registered = $this->service->getRegisteredRoutes();
        $route = end($registered);
        $this->assertInstanceOf(RestRoute::class, $route);

        // Simulate a GET request to the registered route with the custom namespace
        $request = new WP_REST_Request('GET', '/sitchco/custom/custom-namespace');
        $response = rest_do_request($request);

        // Assertions to check the response
        $this->assertEquals(200, $response->get_status());
        $this->assertEquals($data, $response->get_data());
    }

    /**
     * Test that a route with args validates required parameters.
     */
    public function testRouteWithArgsValidation(): void
    {
        $args = [
            'pattern_ids' => [
                'required' => true,
                'type' => 'array',
                'items' => ['type' => 'integer'],
            ],
        ];
        $this->service->addCreateRoute(
            '/with-args',
            fn(WP_REST_Request $request) => ['ids' => $request->get_param('pattern_ids')],
            '',
            $args,
        );

        $registered = $this->service->getRegisteredRoutes();
        $route = end($registered);

        $routes = rest_get_server()->get_routes('sitchco');
        $routeConfig = $routes['/sitchco/with-args'][0];
        $this->assertArrayHasKey('pattern_ids', $routeConfig['args']);
        $this->assertTrue($routeConfig['args']['pattern_ids']['required']);

        // Request missing the required param should fail validation
        $request = new WP_REST_Request('POST', '/sitchco/with-args');
        $response = rest_do_request($request);
        $this->assertEquals(400, $response->get_status());

        // Request with the required param should succeed
        $request = new WP_REST_Request('POST', '/sitchco/with-args');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['pattern_ids' => [1, 2, 3]]));
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(['ids' => [1, 2, 3]], $response->get_data());
    }

    /**
     * Test permission handling for restricted routes.
     */
    public function testRoutePermissionHandling(): void
    {
        $this->service->addReadRoute('/restricted-route', fn() => 'Restricted Content', 'manage_options');

        // Simulate a GET request as an unauthorized user
        $request = new WP_REST_Request('GET', '/sitchco/restricted-route');
        $response = rest_do_request($request);

        // Assertions to check the response
        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('Incorrect permissions for requested route', $response->get_data()['message']);
    }
}
