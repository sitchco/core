<?php

namespace Sitchco\Utils;

enum LogLevel: string
{
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';

    public function meetsThreshold(self $minimum): bool
    {
        return self::severity($this) >= self::severity($minimum);
    }

    private static function severity(self $level): int
    {
        return match ($level) {
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3,
        };
    }
}
