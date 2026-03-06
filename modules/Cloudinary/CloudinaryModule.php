<?php

declare(strict_types=1);

namespace Sitchco\Modules\Cloudinary;

use Sitchco\Framework\Module;
use Sitchco\Support\ImageTransform;
use WP_HTML_Tag_Processor;

class CloudinaryModule extends Module
{
    public function __construct(private CloudinaryUrl $cloudinaryUrl) {}

    public function init(): void
    {
        if (!$this->cloudinaryUrl->isConfigured()) {
            return;
        }
        add_filter('sitchco/image/resize', [$this, 'imageResize'], 10, 2);
        add_filter('wp_content_img_tag', [$this, 'contentImgTag'], 10, 3);
        add_filter('wp_calculate_image_srcset', [$this, 'calculateImageSrcset'], 10, 5);
        add_filter('image_downsize', [$this, 'imageDownsize'], 10, 3);
    }

    public function imageResize(mixed $result, array $imageData): string
    {
        return $this->cloudinaryUrl->buildUrl(
            $imageData['src'],
            new ImageTransform($imageData['width'], $imageData['height'], $imageData['crop']),
        );
    }

    public function contentImgTag(string $image, string $context, int $attachment_id): string
    {
        $processor = new WP_HTML_Tag_Processor($image);
        if (!$processor->next_tag('img')) {
            return $image;
        }
        $src = $processor->get_attribute('src');
        if (!is_string($src)) {
            return $image;
        }
        $newSrc = $this->cloudinaryUrl->buildUrl($src);
        if ($newSrc === $src) {
            return $image;
        }
        $processor->set_attribute('src', $newSrc);
        return $processor->get_updated_html();
    }

    public function calculateImageSrcset(
        array $sources,
        array $size_array,
        string $image_src,
        array $image_meta,
        int $attachment_id,
    ): array {
        foreach ($sources as &$source) {
            $source['url'] = $this->cloudinaryUrl->buildUrl($source['url']);
        }
        return $sources;
    }

    public function imageDownsize(mixed $result, int $id, string|array $size): array|false
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }
        $url = wp_get_attachment_url($id);
        if (!$url) {
            return false;
        }
        $meta = wp_get_attachment_metadata($id);
        if (!$meta) {
            return false;
        }
        $transform = $this->resolveSizeTransform($size, $meta);
        if (!$transform) {
            return false;
        }
        $is_intermediate = $transform->width !== ($meta['width'] ?? 0) || $transform->height !== ($meta['height'] ?? 0);
        return [
            $this->cloudinaryUrl->buildUrl($url, $transform),
            $transform->width,
            $transform->height,
            $is_intermediate,
        ];
    }

    private function resolveSizeTransform(string|array $size, array $meta): ?ImageTransform
    {
        // Array size [w, h] — explicit dimensions, no crop
        if (is_array($size)) {
            return ImageTransform::fromSizeArray($size);
        }
        // 'full' — original dimensions from meta, no crop
        if ($size === 'full') {
            return ImageTransform::fromRegisteredSize($meta);
        }
        $registered = wp_get_registered_image_subsizes();
        // Registered size with crop — use registered dimensions + crop direction
        if (isset($registered[$size]) && $registered[$size]['crop']) {
            return ImageTransform::fromRegisteredSize($registered[$size]);
        }
        // Meta size — actual generated file dimensions (may differ from registered if aspect ratio adjusted)
        if (isset($meta['sizes'][$size])) {
            return ImageTransform::fromRegisteredSize($meta['sizes'][$size]);
        }
        // Registered size without crop — fallback to registered dimensions
        if (isset($registered[$size])) {
            return ImageTransform::fromRegisteredSize($registered[$size]);
        }
        return null;
    }
}
