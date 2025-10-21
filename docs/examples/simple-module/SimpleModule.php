<?php
/**
 * Simple Module Example
 *
 * This is a minimal module demonstrating basic structure and functionality.
 * Copy this file to your theme's modules directory and customize as needed.
 */

namespace Sitchco\App\Modules\Simple;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class SimpleModule extends Module
{
    /**
     * Module initialization
     *
     * IMPORTANT: This is called during after_setup_theme at priority 5,
     * NOT during WordPress's 'init' hook. Use this to REGISTER hooks/filters.
     *
     * This method is always called when the module is activated.
     */
    public function init(): void
    {
        // Register WordPress hooks
        add_action('wp_footer', [$this, 'addFooterContent']);
        add_filter('the_content', [$this, 'modifyContent']);

        // Enqueue frontend assets
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->style('main.css');
            $assets->script('main.js', dependencies: ['jquery']);
        });
    }

    /**
     * Add content to the footer
     *
     * Example of using a WordPress action hook.
     */
    public function addFooterContent(): void
    {
        echo '<!-- Simple Module Active -->';
    }

    /**
     * Modify post content
     *
     * Example of using a WordPress filter hook.
     *
     * @param string $content The post content
     * @return string Modified content
     */
    public function modifyContent(string $content): string
    {
        // Only modify on single posts
        if (!is_single()) {
            return $content;
        }

        // Add custom content before the post content
        $customContent = '<div class="simple-module-notice">This content is enhanced by SimpleModule</div>';

        return $customContent . $content;
    }
}
