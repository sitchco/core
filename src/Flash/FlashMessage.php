<?php

namespace Sitchco\Flash;

/**
 * Class FlashMessage
 * @package Sitchco\FlashMessage
 */
class FlashMessage
{
    protected string $message;

    /**
     * FlashMessage constructor.
     *
     * @param mixed $message The message to store.
     */
    public function __construct(mixed $message)
    {
        $this->message = (string) $message;
    }

    /**
     * Converts the message to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->message;
    }

    /**
     * Returns the message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
