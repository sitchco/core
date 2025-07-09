<?php

namespace Sitchco\Modules\Model;

use DateTimeImmutable;
use Sitchco\Framework\Module;
use Sitchco\Model\Page;
use Sitchco\Model\Post;
use Sitchco\Modules\TimberModule;
use Sitchco\Support\DateTime;

class PostModel extends Module
{
    public const DEPENDENCIES = [TimberModule::class];

    public const POST_CLASSES = [Post::class, Page::class];

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
