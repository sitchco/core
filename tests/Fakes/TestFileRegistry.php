<?php

namespace Sitchco\Tests\Fakes;

use Sitchco\Framework\FileRegistry;
use Sitchco\Support\FilePath;

/**
 * Minimal FileRegistry implementation for testing base class behavior.
 *
 * This fake allows us to test the FileRegistry base class logic (caching, merging,
 * path initialization, error handling) without dealing with actual file I/O.
 */
class TestFileRegistry extends FileRegistry
{
    /** @var string Filename to search for in base paths */
    public const FILENAME = 'test.data';

    /** @var string Filter hook for adding additional paths */
    public const PATH_FILTER_HOOK = 'test_registry_paths';

    /** @var string Cache key for merged data */
    public const CACHE_KEY = 'test_registry_cache';

    /**
     * @var array<string, array|null> Map of file paths to data they should return
     */
    private array $fileData = [];

    /**
     * @var bool Whether parseFile should throw an exception
     */
    private bool $shouldThrow = false;

    /**
     * @var string|null Exception message to throw
     */
    private ?string $exceptionMessage = null;

    /**
     * @var array<string> Track which files were parsed
     */
    private array $parsedFiles = [];

    /**
     * Set the data that should be returned when parsing a specific file.
     *
     * @param string $filePath The file path
     * @param array|null $data The data to return (null to simulate file not returning array)
     * @return self
     */
    public function setFileData(string $filePath, ?array $data): self
    {
        $this->fileData[$filePath] = $data;

        return $this;
    }

    /**
     * Configure parseFile to throw an exception.
     *
     * @param string $message Exception message
     * @return self
     */
    public function setShouldThrow(string $message = 'Test exception'): self
    {
        $this->shouldThrow = true;
        $this->exceptionMessage = $message;

        return $this;
    }

    /**
     * Reset the throw state.
     *
     * @return self
     */
    public function resetThrow(): self
    {
        $this->shouldThrow = false;
        $this->exceptionMessage = null;

        return $this;
    }

    /**
     * Get the list of files that were parsed.
     *
     * @return array<string>
     */
    public function getParsedFiles(): array
    {
        return $this->parsedFiles;
    }

    /**
     * Reset the parsed files list.
     *
     * @return self
     */
    public function resetParsedFiles(): self
    {
        $this->parsedFiles = [];

        return $this;
    }

    /**
     * Parse a file and return its contents.
     * Uses the configured test data instead of actually reading files.
     *
     * @param FilePath $filePath Path to the file to parse
     * @return mixed The configured test data
     * @throws \Exception if configured to throw
     */
    protected function parseFile(FilePath $filePath): mixed
    {
        $path = $filePath->value();
        $this->parsedFiles[] = $path;

        if ($this->shouldThrow) {
            throw new \Exception($this->exceptionMessage ?? 'Test exception');
        }

        // Return configured data if available, otherwise return an empty array
        return $this->fileData[$path] ?? [];
    }
}
