<?php

namespace Sitchco\Modules\UIModal;

use Timber\Post;

readonly class ModalData
{
    private string $id;

    public function __construct(string $id, private string $heading, private string $content, public string $type)
    {
        $id = sanitize_title($id);
        if (preg_match('/^\d/', $id)) {
            $id = 'modal-' . $id;
        }
        $this->id = $id;
    }

    public static function fromPost(Post $post, string $type, bool $excerpt = false): self
    {
        $heading = '';
        if (!preg_match('/<h[1-6][\s>]/i', $post->post_content)) {
            $heading = $post->title();
        }
        $content = $excerpt ? $post->excerpt() : self::renderContentWithInlineStyleRecovery($post);
        return new self($post->slug, $heading, $content, $type);
    }

    /**
     * Renders post content and recovers any inline CSS that was added to already-printed style handles.
     * When do_blocks() runs after wp_head, wp_add_inline_style() on done handles is silently dropped.
     * This captures those orphaned styles and appends them as a <style> block.
     */
    private static function renderContentWithInlineStyleRecovery(Post $post): string
    {
        $wp_styles = wp_styles();
        $done_before = $wp_styles->done;
        $snapshot = [];
        foreach ($done_before as $handle) {
            $snapshot[$handle] = $wp_styles->get_data($handle, 'after') ?: [];
        }

        $content = $post->content();

        $orphaned = [];
        foreach ($done_before as $handle) {
            $after = $wp_styles->get_data($handle, 'after') ?: [];
            $previous = $snapshot[$handle];
            $new_entries = array_slice($after, count($previous));
            if ($new_entries) {
                $orphaned[$handle] = $new_entries;
            }
        }

        if ($orphaned) {
            $css = implode("\n", array_merge(...array_values($orphaned)));
            $content .= "\n<style>\n{$css}\n</style>";
        }

        return $content;
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
