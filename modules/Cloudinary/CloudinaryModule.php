<?php

declare(strict_types=1);

namespace Sitchco\Modules\Cloudinary;

use Sitchco\Framework\Module;
use Sitchco\Support\CropDirection;
use WP_HTML_Tag_Processor;

class CloudinaryModule extends Module
{
    private CloudinaryUrl $cloudinaryUrl;

    public function init(): void
    {
        if (
            !defined('CLOUDINARY_CLOUD_NAME') ||
            !defined('CLOUDINARY_FOLDER') ||
            !CLOUDINARY_CLOUD_NAME ||
            !CLOUDINARY_FOLDER
        ) {
            return;
        }
        $this->cloudinaryUrl = new CloudinaryUrl(CLOUDINARY_CLOUD_NAME, CLOUDINARY_FOLDER);
        add_filter('sitchco/image/resize', [$this, 'imageResize'], 10, 2);
        add_filter('wp_content_img_tag', [$this, 'contentImgTag'], 10, 3);
        add_filter('wp_calculate_image_srcset', [$this, 'calculateImageSrcset'], 10, 5);
        add_filter('image_downsize', [$this, 'imageDownsize'], 10, 3);
    }

    public function imageResize(mixed $result, array $imageData): string
    {
        return $this->cloudinaryUrl->buildUrl(
            $imageData['src'],
            $imageData['width'],
            $imageData['height'],
            $imageData['crop'],
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
        $dimensions = $this->resolveSizeDimensions($size, $meta);
        if (!$dimensions) {
            return false;
        }
        [$width, $height, $crop] = $dimensions;
        $is_intermediate = $width !== ($meta['width'] ?? 0) || $height !== ($meta['height'] ?? 0);
        return [$this->cloudinaryUrl->buildUrl($url, $width, $height, $crop), $width, $height, $is_intermediate];
    }

    private static function parseCrop(bool|array $crop): CropDirection
    {
        if (!is_array($crop)) {
            return CropDirection::CENTER;
        }
        [$x, $y] = $crop;
        if ($y === 'top') {
            return CropDirection::TOP;
        }
        if ($y === 'bottom') {
            return CropDirection::BOTTOM;
        }
        if ($x === 'left') {
            return CropDirection::LEFT;
        }
        if ($x === 'right') {
            return CropDirection::RIGHT;
        }
        return CropDirection::CENTER;
    }

    private function resolveSizeDimensions(string|array $size, array $meta): ?array
    {
        if (is_array($size)) {
            return [(int) $size[0], (int) $size[1], null];
        }
        if ($size === 'full') {
            return [(int) ($meta['width'] ?? 0), (int) ($meta['height'] ?? 0), null];
        }
        $registered = wp_get_registered_image_subsizes();
        if (isset($registered[$size]) && $registered[$size]['crop']) {
            $reg = $registered[$size];
            return [(int) $reg['width'], (int) $reg['height'], self::parseCrop($reg['crop'])];
        }
        if (isset($meta['sizes'][$size])) {
            $s = $meta['sizes'][$size];
            return [(int) $s['width'], (int) $s['height'], null];
        }
        if (isset($registered[$size])) {
            return [(int) $registered[$size]['width'], (int) $registered[$size]['height'], null];
        }
        return null;
    }
}
