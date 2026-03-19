<?php

namespace Sitchco\Modules\CustomTags;

use Sitchco\Framework\Module;

class CustomTags extends Module
{
    public const HOOK_SUFFIX = 'custom-tags';

    public function init(): void
    {
        add_action(
            'admin_menu',
            function () {
                add_submenu_page(
                    'global',
                    'Custom Tags',
                    'Custom Tags',
                    'edit_posts',
                    'edit.php?post_type=sitchco_script',
                );
            },
            100,
        );

        $this->enqueueAdminAssets(function () {
            $screen = get_current_screen();
            if (!$screen || $screen->post_type !== 'sitchco_script') {
                return;
            }
            $settings = wp_enqueue_code_editor(['type' => 'text/html']);
            if ($settings === false) {
                return;
            }
            wp_add_inline_script(
                'code-editor',
                sprintf(
                    'jQuery(function($){var $el=$(".acf-field[data-name=script_content] textarea");if($el.length){wp.codeEditor.initialize($el[0],%s);}});',
                    wp_json_encode($settings),
                ),
            );
        });
    }
}
