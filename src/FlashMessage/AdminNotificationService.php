<?php

namespace Sitchco\FlashMessage;

use Sitchco\Utils\Hooks;

/**
 * class AdminNotificationService
 * @package Sitchco\FlashMessage
 */
class AdminNotificationService
{
    protected array $notifications = [];
    protected static bool $hooked = false;

    public function __construct()
    {
        $this->init();
    }

    public function init(): void
    {
        if (!static::$hooked) {
            Hooks::callOrAddAction('admin_notices', [$this, 'render']);
            Hooks::callOrAddAction('shutdown', [$this, 'shutdown']);
            static::$hooked = true;
        }
    }

    /**
     * Dispatches an admin notification.
     *
     * @param string|array|AdminNotification $message The notification message.
     * @param string $status The status (default: success).
     * @param bool $dismissible Whether dismissible (default: true).
     * @return int The total number of stored notifications.
     */
    public function dispatch(string|array|AdminNotification $message, string $status = AdminNotification::SUCCESS, bool $dismissible = true): int
    {
        if (is_array($message)) {
            $message = implode('</p><p>', array_map(fn($count, $msg) => "$msg: $count", $message, array_keys($message)));
        }

        $notification = $message instanceof AdminNotification ? $message : new AdminNotification($message, $status, $dismissible);
        $this->notifications[] = $notification;
        return count($this->notifications);
    }

    public function dispatchError(string|array|AdminNotification $message, bool $dismissible = true): int
    {
        return $this->dispatch($message, AdminNotification::ERROR, $dismissible);
    }

    public function dispatchInfo(string|array|AdminNotification $message, bool $dismissible = true): int
    {
        return $this->dispatch($message, AdminNotification::INFO, $dismissible);
    }

    public function dispatchWarning(string|array|AdminNotification $message, bool $dismissible = true): int
    {
        return $this->dispatch($message, AdminNotification::WARNING, $dismissible);
    }

    /**
     * Renders and outputs stored admin notifications.
     *
     * @return array The list of notifications displayed.
     */
    public function render(): array
    {
        // Retrieve stored notifications
        $storedNotifications = $this->retrieveStoredNotifications();

        // Merge the flash notification and stored notifications, if any.
        $allNotifications = array_unique(array_merge($storedNotifications, $this->notifications));

        // Output notifications
        foreach ($allNotifications as $notification) {
            echo $notification . "\n";
        }

        // Clear notifications after rendering
        $this->notifications = [];
        return $allNotifications;
    }

    /**
     * Stores notifications for the next request.
     *
     * @return bool True if stored, false if no notifications.
     */
    public function shutdown(): bool
    {
        if (empty($this->notifications)) {
            return false;
        }

        /** @var AdminNotification $notification */
        foreach($this->notifications as $notification) {
            if (!$notification->isDismissible()) {
                $this->storeNotification($notification);
            }
        }
        return true;
    }

    /**
     * Store a notification for the next request.
     *
     * @param AdminNotification $notification
     */
    private function storeNotification(AdminNotification $notification): void
    {
        $notifications = (array) get_option($this->getOptionKey(), []);
        $notifications[] = $notification;
        update_option($this->getOptionKey(), $notifications, false);
    }

    /**
     * Retrieves stored notifications.
     *
     * @return AdminNotification[] Array of notifications.
     */
    private function retrieveStoredNotifications(): array
    {
        $notifications = (array) get_option($this->getOptionKey(), []);
        delete_option($this->getOptionKey());
        return $notifications;
    }

    /**
     * Generates a unique option key for the current user.
     *
     * @return string
     */
    private function getOptionKey(): string
    {
        return sprintf('sitchco_admin_notification_%d', get_current_user_id());
    }
}
