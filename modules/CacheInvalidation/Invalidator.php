<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

abstract class Invalidator
{
    abstract public function slug(): string;

    abstract protected function checkAvailability(): bool;

    abstract public function priority(): int;

    abstract public function delay(): int;

    abstract public function flush(): void;

    public function isAvailable(): bool
    {
        return (bool) apply_filters('sitchco/cache/condition/' . $this->slug(), $this->checkAvailability());
    }
}
