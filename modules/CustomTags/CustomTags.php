<?php

namespace Sitchco\Modules\CustomTags;

use Sitchco\Framework\Module;

class CustomTags extends Module
{
    public const HOOK_SUFFIX = 'custom-tags';

    public function init(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu'], 100);
        $this->enqueueAdminAssets([$this, 'initCodeEditor']);
    }

    public function registerAdminMenu(): void
    {
        global $menu;
        $hasTagManager = false;
        foreach ($menu as $item) {
            if (($item[2] ?? '') === 'tag-manager') {
                $hasTagManager = true;
                break;
            }
        }
        if ($hasTagManager) {
            add_submenu_page(
                'tag-manager',
                'Custom Tags',
                'Custom Tags',
                'edit_posts',
                'edit.php?post_type=sitchco_script',
            );
        } else {
            add_menu_page(
                'Custom Tags',
                'Custom Tags',
                'edit_posts',
                'edit.php?post_type=sitchco_script',
                '',
                'dashicons-code-standards',
                100,
            );
        }
    }

    public function initCodeEditor(): void
    {
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
    }
}
