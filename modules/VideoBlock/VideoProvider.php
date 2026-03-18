<?php

namespace Sitchco\Modules\VideoBlock;

/**
 * Value object encapsulating all provider-specific behavior for video blocks.
 *
 * Centralizes YouTube vs Vimeo differences: URL detection, video ID extraction,
 * display labels, play icon variants, and thumbnail URL upgrades.
 *
 * Unknown providers produce an instance with empty/default values.
 */
readonly class VideoProvider
{
    public const PLAY_ICON_WIDTH = '68';

    private function __construct(
        public string $name,
        public string $label,
        public string $playIconName,
        public string $playIconHeight,
    ) {}

    public static function youtube(): self
    {
        return new self('youtube', 'YouTube', 'youtube-play', '48');
    }

    public static function vimeo(): self
    {
        return new self('vimeo', 'Vimeo', 'generic-play', '68');
    }

    public static function unknown(): self
    {
        return new self('', '', 'generic-play', '68');
    }

    public static function fromName(string $name): self
    {
        return match ($name) {
            'youtube' => self::youtube(),
            'vimeo' => self::vimeo(),
            default => self::unknown(),
        };
    }

    public static function fromUrl(string $url): self
    {
        if (preg_match('#(?:youtube\.com|youtu\.be)/#i', $url)) {
            return self::youtube();
        }
        if (preg_match('#vimeo\.com/#i', $url)) {
            return self::vimeo();
        }
        return self::unknown();
    }

    public function extractVideoId(string $url): string
    {
        if ($this->name === 'youtube') {
            if (
                preg_match(
                    '/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/',
                    $url,
                    $matches,
                )
            ) {
                return $matches[1];
            }
        }
        if ($this->name === 'vimeo') {
            if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * Upgrade oEmbed thumbnail URL to high-resolution variant.
     *
     * YouTube: hqdefault.jpg (480x360 with letterbox) -> maxresdefault.jpg (1280x720).
     * Vimeo: tiny thumbnails (295x166) -> rewrite CDN dimensions to 1280x720.
     */
    public function upgradeThumbnailUrl(string $url, int $width = 0, int $height = 0): string
    {
        if ($this->name === 'youtube') {
            $url = preg_replace('#/hqdefault\.jpg$#', '/maxresdefault.jpg', $url);
        }

        if ($this->name === 'vimeo') {
            $isPortrait = $width && $height && $height > $width;
            $dims = $isPortrait ? '720x1280' : '1280x720';
            $url = preg_replace('/_\d+x\d+/', '_' . $dims, $url);
        }

        return $url;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
