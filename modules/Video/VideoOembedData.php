<?php

namespace Sitchco\Modules\Video;

/**
 * Structured representation of the oEmbed fields used by the video block.
 *
 * Pure value object — no I/O. Constructed from a raw oEmbed stdClass via fromRaw().
 */
readonly class VideoOembedData
{
    public const DEFAULT_ASPECT_WIDTH = 16;
    public const DEFAULT_ASPECT_HEIGHT = 9;

    public bool $hasThumbnail;
    public int $aspectWidth;
    public int $aspectHeight;

    public function __construct(
        public string $thumbnailUrl = '',
        public string $title = '',
        public int $width = 0,
        public int $height = 0,
    ) {
        $this->hasThumbnail = $thumbnailUrl !== '';
        $this->aspectWidth = $width ?: self::DEFAULT_ASPECT_WIDTH;
        $this->aspectHeight = $height ?: self::DEFAULT_ASPECT_HEIGHT;
    }

    /**
     * Build from a raw oEmbed stdClass, upgrading the thumbnail URL via VideoProvider.
     *
     * Returns null if the response indicates a domain-level restriction.
     */
    public static function fromRaw(object $raw, VideoProvider $provider): ?self
    {
        if (isset($raw->domain_status_code) && $raw->domain_status_code >= 400) {
            return null;
        }

        $width = (int) ($raw->width ?? 0);
        $height = (int) ($raw->height ?? 0);
        $thumbnailUrl = !empty($raw->thumbnail_url)
            ? $provider->upgradeThumbnailUrl($raw->thumbnail_url, $width, $height)
            : '';

        return new self($thumbnailUrl, $raw->title ?? '', $width, $height);
    }
}
