<?php

namespace Sitchco\Events;

use Sitchco\Integration\BackgroundProcessing\BackgroundQueueEvent;

/**

 */

class SavePostQueueEvent extends BackgroundQueueEvent
{
    const HOOK_NAME = 'after_save_post';


    public function init(): void
    {
        add_action('wp_after_insert_post', [$this, 'onSavePost'], 10, 2);
    }
    public function onSavePost(int $post_id, \WP_Post $post): void
    {
        $this->enqueue(compact('post_id'), [$post->post_type]);
    }

    public function enqueuePosts(array $args): void
    {
        $query = wp_parse_args($args, [
            'fields' => 'ids',
            'post_type' => 'post',
            'posts_per_page' => -1,
        ]);
        $post_ids = get_posts($query);
        foreach ($post_ids as $post_id) {
            $this->enqueue(compact('post_id'), [$query['post_type']]);
        }
    }
}