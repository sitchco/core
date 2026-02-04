<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\Cron;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Hooks;

class CronTest extends TestCase
{
    private Cron $cron;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cron = $this->container->get(Cron::class);
    }

    public function test_adds_minutely_schedule(): void
    {
        $schedules = apply_filters('cron_schedules', []);

        $this->assertArrayHasKey('minutely', $schedules);
        $this->assertSame(MINUTE_IN_SECONDS, $schedules['minutely']['interval']);
        $this->assertSame('Once Every Minute', $schedules['minutely']['display']);
    }

    public function test_schedules_all_cron_events(): void
    {
        $this->cron->scheduleEvents();

        $this->assertNotFalse(wp_next_scheduled('sitchco_cron_minutely'));
        $this->assertNotFalse(wp_next_scheduled('sitchco_cron_hourly'));
        $this->assertNotFalse(wp_next_scheduled('sitchco_cron_twicedaily'));
        $this->assertNotFalse(wp_next_scheduled('sitchco_cron_daily'));
    }

    public function test_minutely_cron_dispatches_action(): void
    {
        $called = false;
        add_action(Hooks::name('cron', 'minutely'), function () use (&$called) {
            $called = true;
        });

        do_action('sitchco_cron_minutely');

        $this->assertTrue($called, 'sitchco/cron/minutely action should fire when WP cron hook runs');
    }

    public function test_all_intervals_dispatch_namespaced_actions(): void
    {
        $fired = [];

        foreach (['minutely', 'hourly', 'twicedaily', 'daily'] as $interval) {
            add_action(Hooks::name('cron', $interval), function () use (&$fired, $interval) {
                $fired[] = $interval;
            });
        }

        do_action('sitchco_cron_minutely');
        do_action('sitchco_cron_hourly');
        do_action('sitchco_cron_twicedaily');
        do_action('sitchco_cron_daily');

        $this->assertSame(['minutely', 'hourly', 'twicedaily', 'daily'], $fired);
    }
}
