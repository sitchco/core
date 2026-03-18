<?php

namespace Sitchco\Modules\Video;

use WP_Block;

/**
 * Value object representing the resolved attributes for a sitchco/video block.
 *
 * Delegates provider-specific logic to VideoProvider.
 */
readonly class VideoAttributes
{
    public const DEFAULT_PLAY_ICON_STYLE = 'dark';
    public const DEFAULT_PLAY_ICON_X = 50;
    public const DEFAULT_PLAY_ICON_Y = 50;
    public const DEFAULT_CLICK_BEHAVIOR = 'poster';
    public const DEFAULT_DISPLAY_MODE = 'inline';

    public string $url;
    public VideoProvider $provider;
    public string $videoId;
    public string $videoTitle;
    public string $playIconStyle;
    public int|float $playIconX;
    public int|float $playIconY;
    public string $clickBehavior;
    public string $displayMode;
    public string $modalId;
    public bool $hasInnerBlocks;

    public function __construct(array $attributes, WP_Block $block)
    {
        $this->url = $attributes['url'] ?? '';
        $this->videoTitle = $attributes['videoTitle'] ?? '';
        $this->playIconStyle = $attributes['playIconStyle'] ?? self::DEFAULT_PLAY_ICON_STYLE;
        $this->playIconX = $attributes['playIconX'] ?? self::DEFAULT_PLAY_ICON_X;
        $this->playIconY = $attributes['playIconY'] ?? self::DEFAULT_PLAY_ICON_Y;
        $this->clickBehavior = $attributes['clickBehavior'] ?? self::DEFAULT_CLICK_BEHAVIOR;
        $this->provider = !empty($attributes['provider'])
            ? VideoProvider::fromName($attributes['provider'])
            : VideoProvider::fromUrl($this->url);
        $this->videoId = $this->provider->extractVideoId($this->url);
        $this->hasInnerBlocks = count($block->inner_blocks) > 0;

        // Resolve modal ID fallback chain: explicit modalId → slugified title → video ID
        $modalId = $attributes['modalId'] ?? '';
        if (empty($modalId)) {
            $modalId = sanitize_title($this->videoTitle) ?: $this->videoId;
        }
        $this->modalId = $modalId;

        // If modal mode but no ID could be resolved, fall back to inline
        $displayMode = $attributes['displayMode'] ?? self::DEFAULT_DISPLAY_MODE;
        if (($displayMode === 'modal' || $displayMode === 'modal-only') && empty($this->modalId)) {
            $displayMode = self::DEFAULT_DISPLAY_MODE;
        }
        $this->displayMode = $displayMode;
    }

    public function isModal(): bool
    {
        return $this->displayMode === 'modal' || $this->displayMode === 'modal-only';
    }

    public function isModalOnly(): bool
    {
        return $this->displayMode === 'modal-only';
    }

    public function playAriaLabel(): string
    {
        return sprintf('Play video: %s', $this->videoTitle);
    }
}
