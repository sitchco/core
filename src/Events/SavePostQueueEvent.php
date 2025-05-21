<?php

namespace Sitchco\Events;

use Sitchco\BackgroundProcessing\BackgroundActionQueue;
use Sitchco\BackgroundProcessing\BackgroundQueueEvent;
use Sitchco\Utils\Hooks;

/**

 */

class SavePostQueueEvent extends BackgroundQueueEvent
{
    const HOOK_SUFFIX = 'after_save_post';


    public function init(): void
    {
        add_action('wp_after_insert_post', [$this, 'onSavePost'], 10, 2);
        add_action(Hooks::name(BackgroundActionQueue::HOOK_SUFFIX, 'bulk_posts'), [$this, 'onBulkPost']);
    }
    public function onSavePost(int $post_id, \WP_Post $post): void
    {
        $this->enqueue(compact('post_id'), [$post->post_type]);
    }

    public function onBulkPost(\WP_Post $post): void
    {
        $this->enqueue(['post_id' => $post->ID], [$post->post_type]);
    }
}
