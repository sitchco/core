<?php

namespace Sitchco\FlashMessage;

use Sitchco\Utils\Hooks;

/**
 * Class AdminNotificationService
 *
 * This class manages the dispatch, storage, and rendering of admin notifications
 * within the WordPress admin panel.
 *
 * @package Sitchco\FlashMessage
 */
class AdminNotificationService
{
    protected array $notifications = [];

    protected static bool $hooked = false;

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
     * @param string|array|AdminNotification $message The notification message, which can be a string,
     *        an array of messages, or an AdminNotification instance.
     * @param string $status The status of the notification (default: success).
     * @param bool $dismissible Whether the notification should be dismissible (default: true).
     * @return int The total number of stored notifications.
     */
    public function dispatch(string|array|AdminNotification $message, string $status = AdminNotification::SUCCESS, bool $dismissible = true): int
    {
        if (is_array($message)) {
            $message = implode('</p><p>', array_map(fn($count, $msg) => "$msg: $count", $message, array_keys($message)));
        }

        $this->notifications[] = $message instanceof AdminNotification
            ? $message
            : new AdminNotification($message, $status, $dismissible);

        return count($this->notifications);
    }

    /**
     * Renders and outputs stored admin notifications.
     *
     * @return array The list of notifications that were displayed.
     */
    public function render(): array
    {
        $notifications = (array) (FlashMessage::get() ?: $this->notifications);
        echo implode("\n", $notifications);
        $this->notifications = [];
        return $notifications;
    }

    /**
     * Stores notifications to be displayed on the next request.
     *
     * @return bool True if notifications were stored, false if no notifications were available.
     */
    public function shutdown(): bool
    {
        if (empty($this->notifications)) {
            return false;
        }
        FlashMessage::create($this->notifications);
        $this->notifications = [];
        return true;
    }

    /**
     * Handles a CommandResponse or error string and converts it into admin notifications.
     *
     * @param CommandResponse|string $response The command response instance or an error message.
     * @return void
     */
//    public function handleCommandResponse(CommandResponse|string $response): void
//    {
//        if (!$response instanceof CommandResponse) {
//            $commandResponse = new CommandResponse();
//            $commandResponse->addError($response);
//            $response = $commandResponse;
//        }
//
//        $this->dispatch($response->getResults());
//        $this->dispatch($response->getErrors(), AdminNotification::ERROR);
//    }
}
