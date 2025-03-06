<?php

namespace Sitchco\Flash;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Hooks;

/**
 * class Flash
 * @package Sitchco\FlashMessage
 */
class Flash extends Module
{
    protected AdminNotificationService $service;
    public function __construct(AdminNotificationService $service)
    {
        $this->service = $service;
    }

    public function init(): void
    {
        Hooks::callOrAddAction('admin_notices', [$this, 'render']);
        Hooks::callOrAddAction('shutdown', [$this, 'shutdown']);
    }

    /**
     * Renders and outputs stored admin notifications.
     *
     * @return array The list of notifications displayed.
     */
    public function render(): array
    {
        $allNotifications = array_unique($this->retrieveStoredNotifications());
        error_log(var_export($allNotifications, true));
        foreach ($allNotifications as $notification) {
            echo $notification . "\n";
        }

        // Clear notifications after rendering
        $this->service->clearNotifications();

        return $allNotifications;
    }

    /**
     * Stores notifications for the next request.
     *
     * @return bool True if stored, false if no notifications.
     */
    public function shutdown(): bool
    {
        error_log('Has Notifications: ' .  $this->service->hasNotifications());
        if (!$this->service->hasNotifications()) {
            return false;
        }

        foreach($this->service->getNotifications() as $notification) {
            $this->storeNotification($notification);
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