<?php

namespace Sitchco\Model;

use Sitchco\Support\CropDirection;
use Sitchco\Utils\ArrayUtil;
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

    private CropDirection $crop = CropDirection::DEFAULT;

    private bool $lazy = true;

    private array $attrs = [];

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

    public function getSrc()
    {
        if ($this->img_size) {
            $image_data = wp_get_attachment_image_src($this->ID, $this->img_size);
            return $image_data ? $image_data[0] : parent::src();
        }

        if ($this->resize_width || $this->resize_height) {
            // Not cropping
            if ($this->crop === CropDirection::DEFAULT) {
                list($this->resize_width, $this->resize_height) = wp_constrain_dimensions(
                    $this->image_dimensions->width(),
                    $this->image_dimensions->height(),
                    $this->resize_width,
                    $this->resize_height
                );
            }
            return ImageHelper::resize($this->src(), $this->resize_width, $this->resize_height, $this->crop->value);
        }

        return parent::src();
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
}