<?php

namespace Sitchco\Tests\Support;

use Sitchco\Model\Post;

/**
 * class PostTester
 * @package Sitchco\Tests\Support
 */
class PostTester extends Post
{
    public function test_custom_value(): string
    {
        return "Custom Getter: Test Custom Value";
    }
}