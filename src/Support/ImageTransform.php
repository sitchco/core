<?php

namespace Sitchco\Support;

readonly class ImageTransform
{
    public function __construct(public int $width = 0, public int $height = 0, public ?CropDirection $crop = null) {}

    public static function fromSizeArray(array $size): self
    {
        return new self((int) $size[0], (int) $size[1]);
    }

    public static function fromRegisteredSize(array $size): self
    {
        return new self(
            (int) ($size['width'] ?? 0),
            (int) ($size['height'] ?? 0),
            !empty($size['crop']) ? CropDirection::fromImageMeta($size['crop']) : null,
        );
    }
}
