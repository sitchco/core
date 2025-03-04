<?php

namespace Sitchco\FlashMessage;

/**
 * Class CommandResponse
 * @package Sitchco\FlashMessage
 */
class CommandResponse
{
    protected array $results = [];
    protected array $errors = [];
    protected array $data = [];

    /**
     * Adds a result message with an optional count and associated data.
     *
     * @param string $message The result message.
     * @param int $count The number of times this result occurred (default is 1).
     * @param array $data Additional data to store with the result.
     * @return static Returns the current instance for method chaining.
     */
    public function addResult(string $message, int $count = 1, array $data = []): static
    {
        if (!isset($this->results[$message])) {
            $this->results[$message] = 0;
        }
        $this->results[$message] += $count;
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Adds an error message with an optional count.
     *
     * @param string $message The error message.
     * @param int $count The number of times this error occurred (default is 1).
     * @return static Returns the current instance for method chaining.
     */
    public function addError(string $message, int $count = 1): static
    {
        if (!isset($this->errors[$message])) {
            $this->errors[$message] = 0;
        }
        $this->errors[$message] += $count;
        return $this;
    }

    /**
     * Retrieves the stored data.
     *
     * @return array The data associated with results.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Retrieves the stored results.
     *
     * @return array An associative array of result messages and their counts.
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Retrieves the stored errors.
     *
     * @return array An associative array of error messages and their counts.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
