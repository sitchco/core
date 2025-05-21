<?php

namespace Sitchco\Modules;

use Sitchco\Flash\AdminNotification;
use Sitchco\Flash\AdminNotificationService;
use Sitchco\Framework\Module;

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
        add_action('admin_notices', [$this, 'render']);
        add_action('shutdown', [$this, 'shutdown']);
    }

    /**
     * Renders and outputs stored admin notifications.
     *
     * @return array The list of notifications displayed.
     */
    public function render(): array
    {
        $allNotifications = array_unique($this->retrieveStoredNotifications());
        foreach ($allNotifications as $notification) {
            echo $notification . "\n";
        }

        return $allNotifications;
    }

    /**
     * Stores notifications for the next request.
     *
     * @return bool True if stored, false if no notifications.
     */
    public function shutdown(): bool
    {
        if (!$this->service->hasNotifications()) {
            return false;
        }

        $this->storeNotifications($this->service->getNotifications());
        return true;
    }

    /**
     * Store a notification for the next request.
     *
     * @param array $notifications
     */
    private function storeNotifications(array $notifications): void
    {
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
