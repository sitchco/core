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
}
