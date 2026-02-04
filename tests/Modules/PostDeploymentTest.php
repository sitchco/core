<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\PostDeployment;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Hooks;

class PostDeploymentTest extends TestCase
{
    private PostDeployment $module;
    private string $triggerPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(PostDeployment::class);
        $uploadDir = wp_upload_dir();
        $this->triggerPath = $uploadDir['basedir'] . '/.clear-cache';
        // Ensure clean state
        if (file_exists($this->triggerPath)) {
            unlink($this->triggerPath);
        }
    }

    protected function tearDown(): void
    {
        // Clean up trigger file if it exists
        if (file_exists($this->triggerPath)) {
            unlink($this->triggerPath);
        }
        parent::tearDown();
    }

    public function test_fires_deploy_complete_action_when_trigger_file_exists(): void
    {
        $actionFired = false;
        add_action(Hooks::name('deploy', 'complete'), function () use (&$actionFired) {
            $actionFired = true;
        });

        // Create trigger file
        file_put_contents($this->triggerPath, '');

        $this->module->checkTrigger();

        $this->assertTrue($actionFired, 'sitchco/deploy/complete action should fire when trigger file exists');
    }

    public function test_deletes_trigger_file_after_detection(): void
    {
        file_put_contents($this->triggerPath, '');

        $this->module->checkTrigger();

        $this->assertFileDoesNotExist($this->triggerPath, 'Trigger file should be deleted after detection');
    }

    public function test_does_not_fire_action_when_trigger_file_missing(): void
    {
        $actionFired = false;
        add_action(Hooks::name('deploy', 'complete'), function () use (&$actionFired) {
            $actionFired = true;
        });

        // Ensure no trigger file
        $this->assertFileDoesNotExist($this->triggerPath);

        $this->module->checkTrigger();

        $this->assertFalse($actionFired, 'sitchco/deploy/complete action should not fire when trigger file is missing');
    }

    public function test_subscribes_to_minutely_cron(): void
    {
        // Replace the module's checkTrigger with a spy
        $module = new class extends PostDeployment {
            public bool $checkTriggerCalled = false;
            public function checkTrigger(): void
            {
                $this->checkTriggerCalled = true;
            }
        };
        $module->init();

        do_action(Hooks::name('cron', 'minutely'));

        $this->assertTrue($module->checkTriggerCalled, 'checkTrigger should be called on minutely cron');
    }
}
