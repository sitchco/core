<?php

namespace Sitchco\Modules\UIModal;

use Timber\Post;

readonly class ModalData
{
    /**
     * @param Post $post
     * @param ModalType $type
     * @param bool $excerpt
     */
    public function __construct(private Post $post, public ModalType $type, private bool $excerpt = false) {}

    public function id(): string
    {
        return $this->post->slug;
    }

    public function heading(): string
    {
        if (preg_match('/<h[1-6][\s>]/i', $this->post->post_content)) {
            return '';
        }
        return $this->post->title();
    }

    public function content(): string
    {
        return $this->excerpt ? $this->post->excerpt() : $this->post->content();
    }
}
