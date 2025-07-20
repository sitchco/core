<?php

namespace Sitchco\Modules;

use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Framework\Module;

class AdminTools extends Module
{
    const FEATURES = ['resetMetaBoxOrder'];
    /**
     * Enables the user meta box order reset.
     *
     * This method hooks into the Save Permalinks async event hook to trigger the deletion
     * of user meta box locations for all users.
     */
    public function resetMetaBoxOrder(): void
    {
        add_action(SavePermalinksRequestEvent::hookName(), function () use (&$processed) {
            $users = get_users();
            foreach ($users as $user) {
                $user_meta = get_user_meta($user->ID);
                foreach ($user_meta as $meta_key => $meta_value) {
                    if (str_starts_with($meta_key, 'meta-box-order')) {
                        delete_user_meta($user->ID, $meta_key);
                    }
                }
            }
        });
    }
}
