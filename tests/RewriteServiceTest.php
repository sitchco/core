<?php

declare(strict_types=1);

namespace Sitchco\Tests;

use Sitchco\Rewrite\QueryRewrite;
use Sitchco\Rewrite\RedirectRoute;
use Sitchco\Rewrite\RewriteService;
use Sitchco\Rewrite\Route;
use Sitchco\Support\Exception\ExitException;
use Sitchco\Support\Exception\RedirectExitException;
use Sitchco\Tests\Support\TestCase;

/**
 * class RewriteServiceTest
 * @package Sitchco\Tests\Rewrite
 */
class RewriteServiceTest extends TestCase
{
    protected RewriteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(RewriteService::class);
    }

    public function testQueryRewriteRule(): void
    {
        global $wp_rewrite;
        $this->service->register('/custom-path/', ['query' => ['custom' => 'value']]);
        $registered = $this->service->getRegisteredRewriteRules();
        $this->assertInstanceOf(QueryRewrite::class, end($registered));
        $last_rule = array_slice($wp_rewrite->extra_rules_top, -1, 1);
        $this->assertEquals(['/custom-path/' => 'index.php?custom=value'], $last_rule);
        $queryVars = apply_filters('query_vars', []);
        $this->assertContains('custom', $queryVars);
    }

    public function testRouteRule(): void
    {
        global $wp_rewrite;
        $callback = fn() => 'test response';
        $this->service->register('/custom-route/', ['callback' => $callback]);
        $registered = $this->service->getRegisteredRewriteRules();
        $route = end($registered);
        $this->assertInstanceOf(Route::class, $route);
        $last_rule = array_slice($wp_rewrite->extra_rules_top, -1, 1);
        $route_id = 'route_81d6db2488763c8d20514fd2b24a2618';
        $this->assertEquals(['/custom-route/' => "index.php?route=$route_id"], $last_rule);
        $result = $route->processRoute();
        $this->assertFalse($result);
        set_query_var('route', $route_id);
        $result = $route->processRoute();
        $this->assertEquals('test response', $result);
    }

    public function testRedirectRouteRule(): void
    {
        global $wp_rewrite;
        $callback = fn() => true;
        $this->service->register('/logout/', ['callback' => $callback, 'redirect_url' => '/login/']);
        $registered = $this->service->getRegisteredRewriteRules();
        $route = end($registered);
        $this->assertInstanceOf(RedirectRoute::class, $route);
        $last_rule = array_slice($wp_rewrite->extra_rules_top, -1, 1);
        $route_id = 'route_f5ac2f16bee931afff6fb4a5c0069970';
        $this->assertEquals(['/logout/' => "index.php?route=$route_id"], $last_rule);
        $result = $route->processRoute();
        $this->assertFalse($result);
        set_query_var('route', $route_id);
        $this->expectException(RedirectExitException::class);
        $this->expectExceptionMessage('http://example.org/login/');
        $route->processRoute();
    }
}
