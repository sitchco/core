<?php

declare(strict_types=1);

namespace Sitchco\Tests;

use Sitchco\Rewrite\QueryRewrite;
use Sitchco\Rewrite\RewriteService;
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
        $this->service = new RewriteService();
    }

    public function testRegisterAddsRewriteRule(): void
    {
        $this->service->register('/custom-path/', ['custom' => 'value']);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('rewriteRules');
        $property->setAccessible(true);
        $rules = $property->getValue($this->service);

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(QueryRewrite::class, $rules[0]);
    }

    public function testRegisterAllAddsMultipleRewriteRules(): void
    {
        $rules = [
            ['path' => '/first-path/', 'query' => ['var' => 'one']],
            ['path' => '/second-path/', 'query' => ['var' => 'two']]
        ];

        $this->service->registerAll($rules);

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('rewriteRules');
        $property->setAccessible(true);
        $storedRules = $property->getValue($this->service);

        $this->assertCount(2, $storedRules);
        $this->assertInstanceOf(QueryRewrite::class, $storedRules[0]);
        $this->assertInstanceOf(QueryRewrite::class, $storedRules[1]);
    }

    public function testRegisterAllThrowsExceptionOnInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Each rule must have a 'path' key.");

        $this->service->registerAll([
            ['path' => '/valid-path/', 'query' => ['key' => 'value']],
            ['path' => ''] // Invalid entry
        ]);
    }

//    public function testExecuteRegistersRewriteRules(): void
//    {
//        global $wp_rewrite;
//        $this->service->register('/test-path/', ['var' => 'value']);
//        $this->service->execute();
//
//        // Ensure rewrite rules exist after execution
//        $this->assertArrayHasKey('test-path/?$', $wp_rewrite->rules);
//        $this->assertStringContainsString('index.php?var=value', $wp_rewrite->rules['test-path/?$']);
//    }

    public function testExecuteAddsQueryVars(): void
    {
        $this->service->register('/query-var-test/', ['custom_var' => 'value']);
        $this->service->execute();

        $queryVars = apply_filters('query_vars', []);
        $this->assertContains('custom_var', $queryVars);
    }

//    public function testFlushRewriteRulesAfterExecution(): void
//    {
//        global $wp_rewrite;
//        $this->service->register('/flush-test/', ['flush_var' => 'value']);
//        $this->service->execute();
//
//        $this->assertNotEmpty($wp_rewrite->rules, 'Expected rewrite rules to be flushed and not empty.');
//    }
}
