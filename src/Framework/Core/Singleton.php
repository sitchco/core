<?php

namespace Sitchco\Framework\Core;

trait Singleton
{
    private static $instances = [];

    protected function __construct() {}
    final public function __clone() {}
    final public function __sleep() {}
    final public function __wakeup() {}

    final public static function getInstance(): static
    {
        if (!isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
        }
        return self::$instances[static::class];
    }

    /**
     * This can be used to perform additional initialization logic if needed.
     * @return static
     */
    public static function init(): static
    {
        return static::getInstance();
    }
}