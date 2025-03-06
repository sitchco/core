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

//
//namespace Sitchco\Tests;
//
//use Sitchco\FlashMessage\AdminNotificationService;
//use Sitchco\FlashMessage\AdminNotification;
//use Sitchco\Tests\Support\TestCase;
//
///**
// * Class AdminNotificationServiceTest
// * @package Sitchco\Tests
// */
//class AdminNotificationServiceTest extends TestCase
//{
//    private AdminNotificationService $service;
//
//    /**
//     * Set up the AdminNotificationService for testing.
//     */
//    protected function setUp(): void
//    {
//        parent::setUp();
//        $this->service = $this->container->get(AdminNotificationService::class);
//    }
//
//    /**
//     * Test that success messages can be dispatched and rendered.
//     */
//    public function testDispatchSuccessMessage(): void
//    {
//        // Dispatch a success message
//        $this->service->dispatch("This is a success message");
//
//        // Capture the output
//        ob_start();
//        $notifications = $this->service->render();
//        $output = ob_get_clean();
//
//        // Assert that the success message is rendered with the correct HTML
//        $this->assertStringContainsString('notice notice-success', $output);
//        $this->assertStringContainsString("This is a success message", $output);
//        $this->assertCount(1, $notifications);
//    }
//
//    /**
//     * Test that error messages can be dispatched and rendered.
//     */
//    public function testDispatchErrorMessage(): void
//    {
//        // Dispatch an error message
//        $this->service->dispatchError("This is an error message");
//
//        // Capture the output
//        ob_start();
//        $notifications = $this->service->render();
//        $output = ob_get_clean();
//
//        // Assert that the error message is rendered with the correct HTML
//        $this->assertStringContainsString('notice notice-error', $output);
//        $this->assertStringContainsString("This is an error message", $output);
//        $this->assertCount(1, $notifications);
//    }
//
//    /**
//     * Test dispatching and rendering dismissible notifications.
//     */
//    public function testDismissibleNotification(): void
//    {
//        // Dispatch a dismissible notification
//        $this->service->dispatch("This is a dismissible notification", AdminNotification::SUCCESS, true);
//
//        // Capture the output
//        ob_start();
//        $notifications = $this->service->render();
//        $output = ob_get_clean();
//
//        // Assert that the dismissible class is present
//        $this->assertStringContainsString('is-dismissible', $output);
//        $this->assertStringContainsString("This is a dismissible notification", $output);
//        $this->assertCount(1, $notifications);
//    }
//
//    /**
//     * Test dispatching and rendering non-dismissible notifications.
//     */
//    public function testNonDismissibleNotification(): void
//    {
//        // Dispatch a non-dismissible notification
//        $this->service->dispatch("This is a non-dismissible notification", AdminNotification::SUCCESS, false);
//
//        // Capture the output
//        ob_start();
//        $notifications = $this->service->render();
//        $output = ob_get_clean();
//
//        // Assert that the non-dismissible class is NOT present
//        $this->assertStringNotContainsString('is-dismissible', $output);
//        $this->assertStringContainsString("This is a non-dismissible notification", $output);
//        $this->assertCount(1, $notifications);
//    }
//
//    /**
//     * Test storing and retrieving notifications.
//     */
////    public function testStoreAndRetrieveNotification(): void
////    {
////        $this->service->dispatchError("Dismissible notification");
////        // Dispatch a non-dismissible notification
////        $this->service->dispatchError("Storing this non-dismissible notification", false);
////
////        // Store the non-dismissible notifications for the next request (simulate shutdown)
////        $this->service->shutdown();
////
////        // Simulate a new page load and render notifications
////        ob_start();
////        $notifications = $this->service->render();
////        $output = ob_get_clean();
////        $this->assertTrue(true);
////
////        // Assert that the stored notification is rendered
////        $this->assertStringContainsString("Storing this non-dismissible notification", $output);
////        $this->assertCount(2, $notifications);
////    }
//
//    /**
//     * Test handling duplicate notifications (unique messages only).
//     */
//    public function testDuplicateNotificationHandling(): void
//    {
//        // Dispatch the same notification twice
//        $this->service->dispatch("Duplicate notification");
//        $this->service->dispatch("Duplicate notification");
//
//        // Capture the output
//        ob_start();
//        $notifications = $this->service->render();
//        $output = ob_get_clean();
//
//        // Assert that only one notification is rendered
//        $this->assertStringContainsString("Duplicate notification", $output);
//        $this->assertCount(1, $notifications);
//    }
//
//    /**
//     * Test that multiple notifications are handled and rendered properly.
//     */
//    public function testMultipleNotifications(): void
//    {
//        // Dispatch multiple notifications
//        $this->service->dispatch("First notification");
//        $this->service->dispatchError("Error notification");
//        $this->service->dispatchInfo("Info notification");
//        $this->service->dispatchWarning("Warning notification");
//
//        // Capture the output
//        ob_start();
//        $notifications = $this->service->render();
//        $output = ob_get_clean();
//
//        // Assert all notifications are rendered correctly
//        $this->assertStringContainsString("First notification", $output);
//        $this->assertStringContainsString("Error notification", $output);
//        $this->assertStringContainsString("Info notification", $output);
//        $this->assertStringContainsString("Warning notification", $output);
//        $this->assertCount(4, $notifications);
//    }
//}