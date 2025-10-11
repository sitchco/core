<?php

namespace Sitchco\Flash;

/**
 * Class AdminNotificationService
 * @package Sitchco\FlashMessage
 */
class AdminNotificationService
{
    /**
     * @var AdminNotification[] $notifications The list of stored notifications.
     */
    protected array $notifications = [];

    /**
     * Dispatches an admin notification.
     *
     * @param string|array|AdminNotification $message The notification message.
     * @param string $status The status of the notification (default: success).
     * @param bool $dismissible Whether the notification is dismissible (default: true).
     * @return int The total number of stored notifications.
     */
    public function dispatch(
        string|array|AdminNotification $message,
        string $status = AdminNotification::SUCCESS,
        bool $dismissible = true,
    ): int {
        if (is_array($message)) {
            $message = implode(
                '</p><p>',
                array_map(fn($count, $msg) => "$msg: $count", $message, array_keys($message)),
            );
        }

        $notification =
            $message instanceof AdminNotification ? $message : new AdminNotification($message, $status, $dismissible);
        $this->notifications[] = $notification;
        return count($this->notifications);
    }

    /**
     * Dispatches an error notification.
     *
     * @param string|array|AdminNotification $message The error message.
     * @param bool $dismissible Whether the error message is dismissible (default: true).
     * @return int The total number of stored notifications.
     */
    public function dispatchError(string|array|AdminNotification $message, bool $dismissible = true): int
    {
        return $this->dispatch($message, AdminNotification::ERROR, $dismissible);
    }

    /**
     * Dispatches an informational notification.
     *
     * @param string|array|AdminNotification $message The informational message.
     * @param bool $dismissible Whether the message is dismissible (default: true).
     * @return int The total number of stored notifications.
     */
    public function dispatchInfo(string|array|AdminNotification $message, bool $dismissible = true): int
    {
        return $this->dispatch($message, AdminNotification::INFO, $dismissible);
    }

    /**
     * Dispatches a warning notification.
     *
     * @param string|array|AdminNotification $message The warning message.
     * @param bool $dismissible Whether the message is dismissible (default: true).
     * @return int The total number of stored notifications.
     */
    public function dispatchWarning(string|array|AdminNotification $message, bool $dismissible = true): int
    {
        return $this->dispatch($message, AdminNotification::WARNING, $dismissible);
    }

    /**
     * Clears all stored notifications.
     *
     * @return void
     */
    public function clearNotifications(): void
    {
        $this->notifications = [];
    }

    /**
     * Checks if there are any stored notifications.
     *
     * @return bool True if notifications exist, false otherwise.
     */
    public function hasNotifications(): bool
    {
        return !empty($this->notifications);
    }

    /**
     * Retrieves the list of stored notifications.
     *
     * @return AdminNotification[] The list of stored notifications.
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }
}
