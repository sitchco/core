<?php

namespace Sitchco\Tests\Flash;

use Sitchco\Flash\AdminNotificationService;
use Sitchco\Flash\Flash;
use Sitchco\Tests\Support\TestCase;

/**
 * Class FlashTest
 * @package Sitchco\Tests
 */
class FlashTest extends TestCase
{
    private Flash $flash;
    private AdminNotificationService $service;

    /**
     * Set up the Flash service for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminNotificationService();
        $this->flash = new Flash($this->service);
    }

    /**
     * Test that notifications are properly rendered and cleared.
     */
    public function testRender(): void
    {
        $this->service->dispatch("Test notification");
        $this->service->dispatchError("Error notification");
        $this->flash->shutdown();

        ob_start();
        $notifications = $this->flash->render();
        $output = ob_get_clean();

        // Ensure the notifications are displayed
        $this->assertStringContainsString("Test notification", $output);
        $this->assertStringContainsString("Error notification", $output);
        $this->assertCount(2, $notifications);

        // Ensure notifications are cleared after rendering
        $this->assertFalse($this->service->hasNotifications());
    }

    /**
     * Test that notifications are stored and retrieved properly.
     */
    public function testShutdownStoresNotifications(): void
    {
        $this->service->dispatch("Persisted notification");
        $this->service->dispatchError("Persisted error");

        // Store notifications on shutdown
        $this->flash->shutdown();

        // Simulate next request by creating a new instance
        $newFlash = new Flash(new AdminNotificationService());

        ob_start();
        $notifications = $newFlash->render();
        $output = ob_get_clean();

        // Ensure stored notifications are retrieved
        $this->assertStringContainsString("Persisted notification", $output);
        $this->assertStringContainsString("Persisted error", $output);
        $this->assertCount(2, $notifications);
    }
}
