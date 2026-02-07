<?php

namespace Sitchco\Tests\Utils;

use Sitchco\Tests\TestCase;
use Sitchco\Utils\LogLevel;

class DebugTest extends TestCase
{
    public function test_level_meets_threshold_at_same_severity(): void
    {
        $this->assertTrue(LogLevel::INFO->meetsThreshold(LogLevel::INFO));
        $this->assertTrue(LogLevel::WARNING->meetsThreshold(LogLevel::WARNING));
        $this->assertTrue(LogLevel::ERROR->meetsThreshold(LogLevel::ERROR));
    }

    public function test_level_meets_threshold_at_higher_severity(): void
    {
        $this->assertTrue(LogLevel::ERROR->meetsThreshold(LogLevel::WARNING));
        $this->assertTrue(LogLevel::WARNING->meetsThreshold(LogLevel::INFO));
        $this->assertTrue(LogLevel::ERROR->meetsThreshold(LogLevel::DEBUG));
    }

    public function test_level_suppressed_below_threshold(): void
    {
        $this->assertFalse(LogLevel::DEBUG->meetsThreshold(LogLevel::INFO));
        $this->assertFalse(LogLevel::INFO->meetsThreshold(LogLevel::WARNING));
        $this->assertFalse(LogLevel::WARNING->meetsThreshold(LogLevel::ERROR));
    }
}
