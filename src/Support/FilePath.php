<?php

namespace Sitchco\Support;

class FilePath
{

    protected string $filename;
    protected bool $isFile;
    protected bool $isDirectory;

    public function __construct(string $filename)
    {
        $this->filename = wp_normalize_path($filename);
        $this->isFile = is_file($filename);
        $this->isDirectory = is_dir($this->filename);
    }

    public static function createFromClassDir(object $object): static
    {
        $reflector = new \ReflectionClass($object);
        return new static(dirname($reflector->getFileName()));
    }

    public function append(string $relativePath): static
    {
        return new static(static::join($this->dir(), $relativePath));
    }

    public function parent(): static
    {
        return new static(dirname($this->filename));
    }

    public function exists(): string
    {
        return $this->isFile || $this->isDirectory;
    }

    public function isFile(): bool
    {
        return $this->isFile;
    }

    public function isDir(): bool
    {
        return $this->isDirectory;
    }

    public function dir(): string
    {
        return $this->isDirectory ? $this->filename: dirname($this->filename);
    }

    public static function join(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_filter($parts));
    }

    public function url(): string
    {
        $relativeToContent = str_replace(
            wp_normalize_path(WP_CONTENT_DIR),
            '',
            $this->filename
        );

        return content_url($relativeToContent);
    }

    public function value(): string
    {
        return $this->isDir() ? trailingslashit($this->filename) : $this->filename;
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
