<?php

namespace Sitchco\Utils;

/**
 * Class Image
 * @package Sitchco\Utils
 */
class Image
{
    /**
     * Generates an HTML `<img>` tag with a placeholder image.
     *
     * @param int $width  The width of the placeholder image in pixels.
     * @param int $height The height of the placeholder image in pixels.
     *
     * @return string The HTML string for the placeholder image.
     */
    public static function placeholder(int $width, int $height): string
    {
        return sprintf(
            '<img src="http://placekitten.com/%d/%d" width="%d" height="%d" alt="Placeholder Image" />',
            $width,
            $height,
            $width,
            $height,
        );
    }
}
