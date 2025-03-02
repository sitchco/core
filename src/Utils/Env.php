<?php

namespace Sitchco\Utils;

use Sitchco\Support\Exception\ExitException;
use Sitchco\Support\Exception\RedirectExitException;

class Env
{
    public static function isTesting(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL');
    }


    public static function exit(string|ExitException $exception = null): void
    {
        if (static::isTesting()) {
            if (! $exception instanceof ExitException) {
                $exception = new ExitException($exception );
            }
            throw $exception;
        }
        exit;
    }

    public static function redirectExit(string $location, int $status = 302): void
    {
        $exception = null;
        if (static::isTesting()) {
            $exception = new RedirectExitException($location);
        } else {
            wp_redirect($location, $status);
        }
        Env::exit($exception);
    }
}