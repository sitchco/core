<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

interface Invalidator
{
    public function slug(): string;

    public function isAvailable(): bool;

    public function priority(): int;

    public function delay(): int;

    public function flush(): void;
}
