<?php

namespace Sitchco\Tests\Flash;

use Sitchco\Flash\AdminNotificationService;
use Sitchco\Modules\Flash;
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
        $this->service = $this->container->get(AdminNotificationService::class);
        $this->flash = $this->container->get(Flash::class);
    }

    /**
     * Test that notifications are properly rendered and cleared.
     */
    public function testRender(): void
    {
        $this->service->dispatch('Test notification');
        $this->service->dispatchError('Error notification');
        $this->flash->shutdown();

        ob_start();
        $notifications = $this->flash->render();
        $output = ob_get_clean();

        // Ensure the notifications are displayed
        $this->assertStringContainsString('Test notification', $output);
        $this->assertStringContainsString('Error notification', $output);
        $this->assertCount(2, $notifications);
    }
}
