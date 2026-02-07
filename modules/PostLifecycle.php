<?php

declare(strict_types=1);

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

/**
 * Fires meaningful post visibility events by filtering out noise from
 * transition_post_status (autosaves, publish→publish re-saves, etc.).
 *
 * Usage:
 * ```php
 * add_action(PostLifecycle::hookName('visibility_changed'), function ($new_status, $old_status, $post) {
 *     // Only fires when a post enters or leaves 'publish' status
 * }, 10, 3);
 * ```
 */
class PostLifecycle extends Module
{
    public const HOOK_SUFFIX = 'post';

    public function init(): void
    {
        add_action('transition_post_status', [$this, 'onTransitionPostStatus'], 10, 3);
    }

    /**
     * Fire visibility_changed only when a post enters or leaves 'publish' status.
     */
    public function onTransitionPostStatus(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($old_status === $new_status) {
            return;
        }

        if ($old_status !== 'publish' && $new_status !== 'publish') {
            return;
        }

        do_action(self::hookName('visibility_changed'), $new_status, $old_status, $post);
    }
}
