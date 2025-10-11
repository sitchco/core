<?php

namespace Sitchco\Modules\Model;

use Sitchco\Framework\Module;
use Sitchco\Model\Image;
use Sitchco\Model\Page;
use Sitchco\Model\Post;
use Sitchco\Modules\TimberModule;
use Timber\Attachment;
use Timber\PathHelper;
use WP_Post;

class ImageModel extends Module
{
    public const DEPENDENCIES = [TimberModule::class];

    public const POST_CLASSES = [Post::class, Page::class];

    public function init(): void
    {
        add_filter('timber/post/classmap', function ($classmap) {
            $classmap['attachment'] = fn(WP_Post $attachment) => $this->postIsImage($attachment)
                ? Image::class
                : Attachment::class;
            return $classmap;
        });
        add_filter(
            'sitchco/acf_post_type_admin_columns/column_content/thumbnail',
            [$this, 'postThumbnailColumn'],
            5,
            2,
        );
    }

    /**
     * Copied from Timber\Factory\PostFactory::is_image()
     *
     * @param WP_Post $post
     * @return bool
     */
    protected function postIsImage(WP_Post $post)
    {
        $src = \get_attached_file($post->ID);
        $mimes = \wp_get_mime_types();
        $mimes['svg'] = 'image/svg+xml';
        $mimes['webp'] = 'image/webp';
        $check = \wp_check_filetype(PathHelper::basename($src), $mimes);
        $extensions = \apply_filters('timber/post/image_extensions', [
            'jpg',
            'jpeg',
            'jpe',
            'gif',
            'png',
            'svg',
            'webp',
            'avif',
        ]);
        return \in_array($check['ext'], $extensions);
    }

    public function postThumbnailColumn($content, $post_id): string
    {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $attachment = get_post($thumbnail_id);
        if (!$attachment instanceof \WP_Post) {
            return $content;
        }
        $img = Image::build(get_post($thumbnail_id));
        if ($img->height() > 75) {
            $img->setHeight(75);
        }

        return $img->render();
    }
}
