<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;
use Sitchco\Utils\Hooks;

/**
 * Cron event system for scheduling recurring tasks.
 *
 * This module registers WordPress cron schedules and dispatches namespaced hook events
 * at regular intervals. It provides a consistent interface for scheduling tasks without
 * manually managing wp_schedule_event() calls.
 *
 * Available schedules and their corresponding hooks:
 * - minutely:   sitchco/cron/minutely   (every 60 seconds)
 * - hourly:     sitchco/cron/hourly     (every hour)
 * - twicedaily: sitchco/cron/twicedaily (every 12 hours)
 * - daily:      sitchco/cron/daily      (every 24 hours)
 *
 * Usage Examples:
 *
 * // Run a task every minute
 * add_action('sitchco/cron/minutely', function () {
 *     // Check for pending notifications, sync external data, etc.
 * });
 *
 * // Run a cleanup task daily
 * add_action('sitchco/cron/daily', function () {
 *     // Clear expired cache, prune old records, etc.
 * });
 *
 * // Using Hooks utility for dynamic schedule selection
 * add_action(Hooks::name('cron', 'hourly'), [$this, 'syncInventory']);
 *
 * // Multiple handlers on the same schedule with priorities
 * add_action('sitchco/cron/daily', [$this, 'generateReports'], 10);
 * add_action('sitchco/cron/daily', [$this, 'emailReports'], 20);
 */
class Cron extends Module
{
    public const HOOKS = [
        'minutely' => 'sitchco_cron_minutely',
        'hourly' => 'sitchco_cron_hourly',
        'twicedaily' => 'sitchco_cron_twicedaily',
        'daily' => 'sitchco_cron_daily',
    ];

    public function init(): void
    {
        add_filter('cron_schedules', [$this, 'addSchedules']);
        $this->registerDispatchers();
        add_action('init', [$this, 'scheduleEvents']);
    }

    public function addSchedules(array $schedules): array
    {
        if (!isset($schedules['minutely'])) {
            $schedules['minutely'] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'Once Every Minute',
            ];
        }

        return $schedules;
    }

    public function scheduleEvents(): void
    {
        foreach (self::HOOKS as $schedule => $hook) {
            if (wp_next_scheduled($hook)) {
                continue;
            }

            wp_schedule_event(time(), $schedule, $hook);
        }
    }

    private function registerDispatchers(): void
    {
        foreach (self::HOOKS as $schedule => $hook) {
            add_action($hook, function () use ($schedule) {
                do_action(Hooks::name('cron', $schedule));
            });
        }
    }
}
