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
        if (str_contains($this->post->post_content, '<h')) {
            return '';
        }
        return $this->post->title();
    }

    public function content(): string
    {
        return $this->excerpt ? $this->post->excerpt() : $this->post->content();
    }
}
