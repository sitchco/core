<?php

namespace Sitchco\Framework\Core;

trait Singleton
{
    private static $instances = [];

    protected function __construct() {}
    final public function __clone() {}
    final public function __sleep() {}
    final public function __wakeup() {}

    final public static function init()
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
        }
        return self::$instances[static::class];
    }
}