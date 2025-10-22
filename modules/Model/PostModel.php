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
        add_filter('acf/format_value/type=date_picker', [$this, 'transformDatePicker'], 11, 1);
        add_filter('acf/format_value/type=date_time_picker', [$this, 'transformDatePicker'], 11, 1);
    }

    /**
     * Transform ACF date picker field
     * @param string $value
     */
    public static function transformDatePicker($value)
    {
        if (!$value) {
            return $value;
        }
        return new DateTime($value);
    }
}
