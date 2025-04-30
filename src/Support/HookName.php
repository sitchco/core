<?php

namespace Sitchco\Support;

class HookName
{
    /** @var string The root namespace for hooks. */

    public const ROOT = 'sitchco';

    protected array $parts;

    public function __construct(string ...$parts)
    {
        $this->parts = $parts;
    }

    public static function fromArray(array $parts) : static
    {
        return new static(...$parts);
    }

    public function append(string ...$parts): static
    {
        return static::fromArray(array_merge($this->parts, $parts));
    }

    public function prepend(string ...$parts): string
    {
        return static::fromArray(array_merge($parts, $this->parts));
    }

    public static function join(string ...$parts): string
    {
        return implode('/', array_filter($parts));
    }

    public function parts(): array
    {
        return $this->parts;
    }


    public function value(): string
    {
        return static::join(self::ROOT, ...$this->parts);
    }

    public function __toString(): string
    {
        return $this->value();
    }
}