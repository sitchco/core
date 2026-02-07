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
        add_action('wp_after_insert_post', [$this, 'onAfterInsertPost'], 10, 4);
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

    /**
     * Fire content_updated for published post saves, filtering out revisions and autosaves.
     */
    public function onAfterInsertPost(int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before): void
    {
        if ($post->post_type === 'revision') {
            return;
        }

        if (wp_is_post_autosave($post_id)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        do_action(self::hookName('content_updated'), $post_id, $post, $update, $post_before);
    }
}
