<?php


namespace Sitchco\Tests\Flash;

use Sitchco\Flash\AdminNotification;
use Sitchco\Flash\AdminNotificationService;
use Sitchco\Tests\Support\TestCase;

/**
 * Class AdminNotificationServiceTest
 * @package Sitchco\Tests
 */
class AdminNotificationServiceTest extends TestCase
{
    private AdminNotificationService $service;

    /**
     * Set up the AdminNotificationService for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(AdminNotificationService::class);
        $this->service->clearNotifications();
    }

    /**
     * Test that success messages can be dispatched and retrieved.
     */
    public function testDispatchSuccessMessage(): void
    {
        $this->service->dispatch("This is a success message");
        $notifications = $this->service->getNotifications();

        $this->assertCount(1, $notifications);
        $this->assertEquals("This is a success message", $notifications[0]->getMessage());
        $this->assertEquals(AdminNotification::SUCCESS, $notifications[0]->getStatus());
    }

    /**
     * Test that error messages can be dispatched and retrieved.
     */
    public function testDispatchErrorMessage(): void
    {
        $this->service->dispatchError("This is an error message");
        $notifications = $this->service->getNotifications();

        $this->assertCount(1, $notifications);
        $this->assertEquals("This is an error message", $notifications[0]->getMessage());
        $this->assertEquals(AdminNotification::ERROR, $notifications[0]->getStatus());
    }

    /**
     * Test handling duplicate notifications (unique messages only).
     */
    public function testDuplicateNotificationHandling(): void
    {
        $this->service->dispatch("Duplicate notification");
        $this->service->dispatch("Duplicate notification");
        $notifications = $this->service->getNotifications();

        $this->assertCount(2, $notifications);
    }

    /**
     * Test that multiple notifications are stored and retrieved properly.
     */
    public function testMultipleNotifications(): void
    {
        $this->service->dispatch("First notification");
        $this->service->dispatchError("Error notification");
        $this->service->dispatchInfo("Info notification");
        $this->service->dispatchWarning("Warning notification");

        $notifications = $this->service->getNotifications();

        $this->assertCount(4, $notifications);
        $this->assertEquals("First notification", $notifications[0]->getMessage());
        $this->assertEquals("Error notification", $notifications[1]->getMessage());
        $this->assertEquals("Info notification", $notifications[2]->getMessage());
        $this->assertEquals("Warning notification", $notifications[3]->getMessage());
    }

    /**
     * Test clearing notifications.
     */
    public function testClearNotifications(): void
    {
        $this->service->dispatch("Notification to be cleared");
        $this->service->clearNotifications();
        $notifications = $this->service->getNotifications();

        $this->assertCount(0, $notifications);
    }
}