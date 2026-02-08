<?php

declare(strict_types=1);

namespace Sitchco\Modules;

use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;
use Sitchco\Utils\Logger;
use Sitchco\Utils\Hooks;

/**
 * Detects deployment and migration completion events and fires a normalized action.
 *
 * Fires `sitchco/deploy/complete` when:
 * - A `.clear-cache` trigger file is found in the uploads directory (checked on minutely cron)
 * - A WP Migrate DB Pro migration completes (`wpmdb_migration_complete`)
 *
 * Usage:
 * ```php
 * add_action('sitchco/deploy/complete', function () {
 *     // Run post-deployment tasks (cache invalidation, etc.)
 * });
 * ```
 */
class PostDeployment extends Module
{
    public const HOOK_SUFFIX = 'deploy';

    private const TRIGGER_FILENAME = '.clear-cache';

    public function init(): void
    {
        add_action(Hooks::name('cron', 'minutely'), [$this, 'checkTrigger']);
        add_action('wpmdb_migration_complete', [$this, 'onMigrationComplete']);
    }

    /**
     * Fire deployment complete action when a WP Migrate DB migration finishes.
     */
    public function onMigrationComplete(): void
    {
        do_action(self::hookName('complete'));
    }

    /**
     * Check for trigger file and fire deployment complete action if found.
     */
    public function checkTrigger(): void
    {
        $triggerPath = $this->getTriggerPath();

        if (!$triggerPath->exists()) {
            return;
        }

        // Delete first, only fire action if deletion succeeds
        // This prevents infinite loops if deletion fails (e.g., permissions)
        $deleted = @unlink($triggerPath->value());
        $triggerPath->reset();

        if ($deleted || !$triggerPath->exists()) {
            do_action(self::hookName('complete'));
        } else {
            Logger::log('Failed to delete trigger file: ' . $triggerPath->value());
        }
    }

    /**
     * Get the path to the trigger file in the uploads directory.
     */
    protected function getTriggerPath(): FilePath
    {
        $uploadDir = wp_upload_dir();
        return FilePath::create($uploadDir['basedir'] . '/' . self::TRIGGER_FILENAME);
    }
}
