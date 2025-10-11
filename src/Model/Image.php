<?php

namespace Sitchco\Model;

use Sitchco\Support\CropDirection;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Hooks;
use Timber\Loader;
use Timber\Timber;
use Timber\ImageHelper;

/**
 * class Image
 * @package Sitchco\Model
 *
 */
class Image extends \Timber\Image
{
    const TEMPLATE = 'image.twig';
    private string|null $img_size = null;
    private int|null $resize_width = null;
    private int|null $resize_height = null;

    private CropDirection $crop = CropDirection::NONE;

    private bool $lazy = true;

    private array $attrs = [];

    public static function buildFromAttachmentId(int $attachment_id): static
    {
        return static::build(get_post($attachment_id));
    }

    public function attrs(): array
    {
        return $this->attrs;
    }

    public function attrsHtml(): string
    {
        return ArrayUtil::toAttributes($this->attrs);
    }

    public function setAttr(string $key, $value): static
    {
        $this->attrs[$key] = $value;
        return $this;
    }

    public function lazy(): bool
    {
        return $this->lazy;
    }

    public function setLazy(bool $lazy): static
    {
        $this->lazy = $lazy;
        return $this;
    }

    public function crop(CropDirection $direction = CropDirection::CENTER): static
    {
        $this->crop = $direction;
        return $this;
    }

    public function setSize(string $size): static
    {
        $this->img_size = $size;
        $this->resize_width = null;
        $this->resize_height = null;
        return $this;
    }

    public function width(): int
    {
        return $this->resize_width ?: parent::width();
    }

    public function height(): int
    {
        return $this->resize_height ?: parent::height();
    }

    public function resizeAspect(): float
    {
        return $this->resize_width / $this->resize_height;
    }

    public function setWidth(int $resize_width): static
    {
        $this->resize_width = $resize_width;
        $this->img_size = null;
        return $this;
    }

    public function setHeight(int $resize_height): static
    {
        $this->resize_height = $resize_height;
        $this->img_size = null;
        return $this;
    }

    public function resize(int $width, int $height): static
    {
        $this->resize_width = $width;
        $this->resize_height = $height;
        $this->img_size = null;
        return $this;
    }

    public function resizedSrc()
    {
        // Use defined WP image size
        if ($this->img_size) {
            $image_data = wp_get_attachment_image_src($this->ID, $this->img_size);
            return $image_data ? $image_data[0] : $this->src();
        }

        $this->fillMissingDimension();

        // Use original image if resizing not needed
        if (!$this->shouldResize()) {
            return $this->src();
        }

        [$width, $height] = $this->getDimensionsForResizing();
        $crop = $this->getCropForResizing();

        // Hook to bypass filesystem resize: sitchco/image/resize
        $resized = apply_filters(Hooks::name('image/resize'), false, [
            'src' => $this->src(),
            'width' => $width,
            'height' => $height,
            'crop' => $crop,
        ]);
        if (false !== $resized) {
            return $resized;
        }

        return ImageHelper::resize($this->src(), $width, $height, $crop->value);
    }

    public function render(): string
    {
        $output = '';
        $loader = (new Loader())->get_loader();
        if ($loader->exists(static::TEMPLATE)) {
            $result = Timber::compile(static::TEMPLATE, ['image' => $this]);
            if ($result) {
                $output = $result;
            }
        }
        return $output;
    }

    public function __toString(): string
    {
        return $this->render() ?: parent::__toString();
    }

    public static function create(): Image
    {
        return new static();
    }

    public function isLargerSize(bool $in_both_dimensions = true): bool
    {
        $larger_width = $this->resize_width > parent::width();
        $larger_height = $this->resize_height > parent::height();
        return $in_both_dimensions ? $larger_width && $larger_height : $larger_width || $larger_height;
    }

    public function isSmallerSize(bool $in_both_dimensions = true): bool
    {
        $smaller_width = $this->resize_width < parent::width();
        $smaller_height = $this->resize_height < parent::height();
        return $in_both_dimensions ? $smaller_width && $smaller_height : $smaller_width || $smaller_height;
    }

    private function hasBothDimensions(): bool
    {
        return isset($this->resize_width) && isset($this->resize_height);
    }

    private function isSameSize(): bool
    {
        return $this->resize_width === parent::width() && $this->resize_height === parent::height();
    }

    private function getDimensionsForResizing(): array
    {
        $width = $this->resize_width;
        $height = $this->resize_height;
        // Larger image with one dimension, calculate missing width or height
        if ($this->isLargerSize()) {
            [$width, $height] = wp_constrain_dimensions(
                $this->resize_width,
                $this->resize_height,
                parent::width(),
                parent::height(),
            );
        }
        return [$width, $height];
    }

    private function fillMissingDimension(): void
    {
        if ($this->hasBothDimensions() || $this->isSameSize()) {
            return;
        }
        if ($this->isLargerSize(false)) {
            if (!$this->resize_width) {
                $this->resize_width = round(parent::width() * ($this->resize_height / parent::height()));
            }
            if (!$this->resize_height) {
                $this->resize_height = round(parent::height() * ($this->resize_width / parent::width()));
            }
            return;
        }
        [$this->resize_width, $this->resize_height] = wp_constrain_dimensions(
            parent::width(),
            parent::height(),
            $this->resize_width,
            $this->resize_height,
        );
    }

    private function getCropForResizing(): CropDirection
    {
        if ($this->resizeAspect() == $this->aspect()) {
            return CropDirection::NONE;
        }
        return $this->crop === CropDirection::NONE ? CropDirection::CENTER : $this->crop;
    }

    private function shouldResize(): bool
    {
        // same size image
        if ($this->isSameSize()) {
            return false;
        }
        $crop = $this->getCropForResizing();
        if ($this->isLargerSize() && $crop === CropDirection::NONE) {
            return false;
        }
        return true;
    }
}
