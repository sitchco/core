<?php

namespace Sitchco\Model;

use DateTimeImmutable;
use Sitchco\Framework\Core\Module;
use Sitchco\Integration\Timber;
use Sitchco\Support\DateTime;
use Timber\Integration\AcfIntegration;

class PostModel extends Module
{
    public const DEPENDENCIES = [
        Timber::class
    ];

    public const POST_CLASSES = [
        Post::class,
        Page::class,
    ];

    public function init(): void
    {
        add_filter('timber/post/pre_meta', [$this, 'transformDateMeta']);
    }

    /**
     * Transform date meta field
     *
     * @param mixed $value
     */
    public function transformDateMeta(mixed $value)
    {
        if ($value instanceof DateTimeImmutable) {
            return new DateTime($value);
        }
        return $value;
    }
}