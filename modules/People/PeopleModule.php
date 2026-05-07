<?php

namespace Sitchco\Modules\People;

use Sitchco\Framework\Module;
use Sitchco\Modules\TimberModule;
use Sitchco\Modules\UIModal\UIModal;

class PeopleModule extends Module
{
    const DEPENDENCIES = [TimberModule::class, UIModal::class];

    public const HOOK_SUFFIX = 'people';

    public const POST_CLASSES = [PersonPost::class];

    public function init(): void
    {
        add_filter(
            'enter_title_here',
            function ($text, $post) {
                return $post->post_type === 'person' ? __('Add Name', 'sitchco') : $text;
            },
            10,
            2,
        );
        add_filter('manage_person_posts_columns', function ($columns) {
            $columns['title'] = __('Name', 'sitchco');
            return $columns;
        });
    }
}
