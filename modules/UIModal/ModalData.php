<?php

namespace Sitchco\Modules\UIModal;

use Timber\Post;

readonly class ModalData
{
    private string $id;

    public function __construct(string $id, private string $heading, private string $content, public ModalType $type)
    {
        $id = sanitize_title($id);
        if (preg_match('/^\d/', $id)) {
            $id = 'modal-' . $id;
        }
        $this->id = $id;
    }

    public static function fromPost(Post $post, ModalType $type, bool $excerpt = false): self
    {
        $heading = '';
        if (!preg_match('/<h[1-6][\s>]/i', $post->post_content)) {
            $heading = $post->title();
        }
        $content = $excerpt ? $post->excerpt() : $post->content();
        return new self($post->slug, $heading, $content, $type);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function heading(): string
    {
        return $this->heading;
    }

    public function content(): string
    {
        return $this->content;
    }
}
