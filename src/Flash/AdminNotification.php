<?php

namespace Sitchco\Flash;

/**
 * Class AdminNotification
 * @package Sitchco\FlashMessage
 */
class AdminNotification extends FlashMessage
{
    public const SUCCESS = 'success';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    private string $status;
    private bool $dismissible;

    /**
     * AdminNotification constructor.
     *
     * @param mixed  $message     Message to display.
     * @param string $status      One of SUCCESS, INFO, WARNING, ERROR.
     * @param bool   $dismissible Whether the notification is dismissible.
     */
    public function __construct(mixed $message, string $status = self::SUCCESS, bool $dismissible = true)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->dismissible = $dismissible;
    }

    /**
     * Converts the notification to a string (HTML).
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '<div class="notice notice-%s%s"><p>%s</p></div>',
            $this->status,
            $this->dismissible ? ' is-dismissible' : '',
            $this->message
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
