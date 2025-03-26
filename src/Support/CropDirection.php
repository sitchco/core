<?php

namespace Sitchco\Support;

/**
 * Timber crop directions: https://timber.github.io/docs/v2/reference/timber-imagehelper/#resize
 */
enum CropDirection: string
{
    case DEFAULT = 'default';
    case CENTER = 'center';
    case TOP = 'top';
    case BOTTOM = 'bottom';
    case LEFT = 'left';
    case RIGHT = 'right';
    case TOP_CENTER = 'top-center';
    case BOTTOM_CENTER = 'bottom-center';
}
