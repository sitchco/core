<?php

namespace Sitchco\Tests;

use DI\Container;

abstract class TestCase extends \WPTest\Test\TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        $this->container = $GLOBALS['SitchcoContainer'];
        parent::setUp();
    }

    protected function fakeHttp(): void
    {
        add_filter(
            'pre_http_request',
            function ($preempt, $args, $url) {
                return [
                    'url' => $url,
                    'method' => $args['method'],
                    'headers' => $args['headers'],
                    'body' => $args['body'],
                ];
            },
            1,
            3
        );
    }

    protected function restoreHttp(): void
    {
        remove_all_filters('pre_http_request');
    }
}
