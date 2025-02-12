<?php

namespace Sitchco\Model;

use Sitchco\Framework\Core\Module;
use Sitchco\Integration\Timber;

class PostModel extends Module
{
    public const DEPENDENCIES = [
        Timber::class
    ];

    public const POST_CLASSES = [
        Post::class,
        Page::class,
    ];
}