<?php

namespace Sitchco\FlashMessage;

/**
 * class AdminNotification
 * @package Sitchco\FlashMessage
 */
class AdminNotification
{
    public const SUCCESS = 'success';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    private string $message;
    private string $status;
    private bool $dismissible;

    /**
     * AdminNotification constructor.
     *
     * @param mixed  $message     Message to display
     * @param string $status      One of AdminNotification::SUCCESS, INFO, WARNING, ERROR
     * @param bool   $dismissible Whether the notification is dismissible
     */
    public function __construct(mixed $message, string $status = self::SUCCESS, bool $dismissible = true)
    {
        $this->message = (string) $message;
        $this->status = $status ?: self::SUCCESS;
        $this->dismissible = $dismissible;
    }

    /**
     * Converts the notification to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '<div class="notice notice-%s%s"><p>%s</p></div>',
            $this->status,
            ($this->dismissible ? ' is-dismissible' : ''),
            $this->message
        );
    }
}
