<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Utils\Logger;

final readonly class PendingInvalidation
{
    public function __construct(public string $slug, public int $expires, public int $delay) {}

    public static function fromInvalidator(Invalidator $invalidator, ?int $now = null): self
    {
        $now ??= time();

        return new self(
            slug: $invalidator->slug(),
            expires: $now + $invalidator->delay(),
            delay: $invalidator->delay(),
        );
    }

    public static function fromArray(array $row): ?self
    {
        if (!isset($row['slug'], $row['expires'], $row['delay'])) {
            Logger::warning('[Cache] Dropping malformed queue row: missing keys');

            return null;
        }

        if (!is_string($row['slug']) || !is_numeric($row['expires']) || !is_numeric($row['delay'])) {
            Logger::warning('[Cache] Dropping malformed queue row: invalid types');

            return null;
        }

        return new self(slug: $row['slug'], expires: (int) $row['expires'], delay: (int) $row['delay']);
    }

    public function isExpired(?int $now = null): bool
    {
        return ($now ?? time()) >= $this->expires;
    }

    public function refresh(?int $now = null): self
    {
        $now ??= time();

        return new self($this->slug, $now + $this->delay, $this->delay);
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'expires' => $this->expires, 'delay' => $this->delay];
    }
}
