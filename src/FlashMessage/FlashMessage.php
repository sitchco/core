<?php

namespace Sitchco\FlashMessage;

/**
 * Class FlashMessage
 *
 * Handles storing and retrieving flash messages.
 *
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

    public function getMessage(): string
    {
        return $this->message;
    }
}