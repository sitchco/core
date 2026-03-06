<?php

namespace Sitchco\Support;

/**
 * Timber crop directions: https://timber.github.io/docs/v2/reference/timber-imagehelper/#resize
 */
enum CropDirection: string
{
    case NONE = 'default';
    case CENTER = 'center';
    case TOP = 'top';
    case BOTTOM = 'bottom';
    case LEFT = 'left';
    case RIGHT = 'right';
    case TOP_CENTER = 'top-center';
    case BOTTOM_CENTER = 'bottom-center';

    public static function fromImageMeta(bool|array $crop): self
    {
        if (!is_array($crop)) {
            return self::CENTER;
        }
        [$x, $y] = $crop;
        return match (true) {
            $y === 'top' => self::TOP,
            $y === 'bottom' => self::BOTTOM,
            $x === 'left' => self::LEFT,
            $x === 'right' => self::RIGHT,
            default => self::CENTER,
        };
    }
}
