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
        add_filter('timber/post/pre_meta', [$this, 'transformDateMeta'], 99, 3);
    }

    /**
     * Transform date meta field
     *
     * @param mixed $value
     */
    public function transformDateMeta(mixed $value, ?int $post_id, ?string $field_name)
    {
        if (function_exists('get_field_object')) {
            $field_object = get_field_object($field_name, $post_id);
            if ($field_object && in_array($field_object['type'], ['date_picker', 'date_time_picker'])) {
                if (empty($value)) {
                    return null;
                }
                try {
                    return new DateTime($value);
                } catch (\Exception $e) {
                    error_log("Error parsing date for post_id: {$post_id}, key: {$field_name}, value: '{$value}'. Field Type: {$field_object['type']}. Error: " . $e->getMessage());
                    return null;
                }
            }
        }

        return $value;
    }
}
