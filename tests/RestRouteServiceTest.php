<?php
//
//namespace Sitchco\Tests;
//
//use Sitchco\Rest\RestRouteService;
//use Sitchco\Tests\Support\TestCase;
//use WP_REST_Request;
//use WP_REST_Response;
//
///**
// * class RestRouteServiceTest
// * @package Sitchco\Tests
// */
//class RestRouteServiceTest extends TestCase
//{
//    private RestRouteService $restRouteService;
//
//    protected function setUp(): void
//    {
//        // Initialize the RestRouteService before each test
//        $this->restRouteService = new RestRouteService();
//    }
//
//    /**
//     * Test that a read route can be added and handled correctly.
//     */
//    public function testAddReadRoute(): void
//    {
//        // Add the read route with a callback that returns a response
//        $this->restRouteService->addReadRoute('/example-read', function () {
//            return new WP_REST_Response('Read Route Response', 200);
//        });
//
//        // Simulate a GET request to the registered route
//        $request = new WP_REST_Request('GET', '/wp-json/sitchco/v1/example-read');
//        $response = rest_do_request($request);
//
//        // Assertions to check the response
//        $this->assertEquals(200, $response->get_status());
//        $this->assertEquals('Read Route Response', $response->get_data());
//    }
//
//    /**
//     * Test that a create route can be added and handled correctly.
//     */
//    public function testAddCreateRoute(): void
//    {
//        // Add the create route with a callback that returns a response
//        $this->restRouteService->addCreateRoute('/example-create', function () {
//            return new WP_REST_Response('Create Route Response', 200);
//        });
//
//        // Simulate a POST request to the registered route
//        $request = new WP_REST_Request('POST', '/wp-json/sitchco/v1/example-create');
//        $response = rest_do_request($request);
//
//        // Assertions to check the response
//        $this->assertEquals(200, $response->get_status());
//        $this->assertEquals('Create Route Response', $response->get_data());
//    }
//
//    /**
//     * Test that routes can be registered with a custom namespace.
//     */
//    public function testNamespaceCustomization(): void
//    {
//        // Initialize the RestRouteService with a custom namespace
//        $customNamespace = 'custom/v1';
//        $restRouteService = new RestRouteService($customNamespace);
//
//        // Add a read route with a callback that returns a response
//        $restRouteService->addReadRoute('/custom-namespace', function () {
//            return new WP_REST_Response('Custom Namespace Route', 200);
//        });
//
//        // Simulate a GET request to the registered route with the custom namespace
//        $request = new WP_REST_Request('GET', '/wp-json/custom/v1/custom-namespace');
//        $response = rest_do_request($request);
//
//        // Assertions to check the response
//        $this->assertEquals(200, $response->get_status());
//        $this->assertEquals('Custom Namespace Route', $response->get_data());
//    }
//
//    /**
//     * Test that routes can be registered without specifying a namespace.
//     */
//    public function testDefaultNamespace(): void
//    {
//        // Add a read route with the default namespace
//        $this->restRouteService->addReadRoute('/default-namespace', function () {
//            return new WP_REST_Response('Default Namespace Route', 200);
//        });
//
//        // Simulate a GET request to the registered route with the default namespace
//        $request = new WP_REST_Request('GET', '/wp-json/sitchco/v1/default-namespace');
//        $response = rest_do_request($request);
//
//        // Assertions to check the response
//        $this->assertEquals(200, $response->get_status());
//        $this->assertEquals('Default Namespace Route', $response->get_data());
//    }
//}
