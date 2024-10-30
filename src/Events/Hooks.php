<?php

namespace Sitchco\Events;

use Sitchco\Framework\Core\Singleton;

class Hooks
{
    use Singleton;

    protected function __construct()
    {
        add_action('current_screen', [$this, 'onSavePermalinks']);
    }

    public function onSavePermalinks($screen): void
    {
        if ($screen->id === 'options-permalink' && ! empty($_POST['permalink_structure'])) {
            do_action('sitchco/after_save_permalinks');
        }
    }
}