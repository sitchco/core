<?php

namespace Sitchco\Support;

class FilePath
{
    protected string $filename;
    protected bool $isFile;
    protected bool $isDirectory;

    public function __construct(string $filename)
    {
        $this->build($filename);
    }

    public function reset(): void
    {
        $this->build($this->filename);
    }

    protected function build(string $filename): void
    {
        $this->filename = wp_normalize_path($filename);
        $this->isFile = is_file($this->filename);
        $this->isDirectory = is_dir($this->filename);
    }

    public static function create(string $filename): self
    {
        return new static($filename);
    }

    public function clone(): static
    {
        return new static($this->filename);
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

    public function findAncestor(string $filename): ?static
    {
        $dir = $this->isDirectory ? $this : $this->parent();
        do {
            if ($dir->append($filename)->exists()) {
                return $dir;
            }
            $dir = $dir->parent();
        } while (!$dir->isRoot());
        return null;
    }

    public function exists(): bool
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

    public function isRoot(): bool
    {
        if (!$this->isDirectory) {
            return false;
        }
        return $this->filename === dirname($this->filename);
    }

    public function name(): string
    {
        return pathinfo($this->value(), PATHINFO_FILENAME);
    }

    public function dir(): string
    {
        return $this->isDirectory ? $this->filename : dirname($this->filename);
    }

    public static function join(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_filter($parts));
    }

    public function relativeTo(string $rootPath): string
    {
        return str_replace($rootPath, '', $this->value());
    }

    /**
     * @param string $pattern
     * @return FilePath[]
     */
    public function glob(string $pattern): array
    {
        $filenames = glob($this->append($pattern)->value());
        return array_map([self::class, 'create'], $filenames);
    }

    public function url(): string
    {
        $relativeToContent = str_replace(wp_normalize_path(WP_CONTENT_DIR), '', $this->filename);

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
