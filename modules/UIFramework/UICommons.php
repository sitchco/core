<?php

namespace Sitchco\Modules\CommonUI;

use Sitchco\Framework\Module;

class UIFramework extends Module
{
    public function init(): void
    {
        add_action('init', [$this, 'setupAssets'], 5);
    }

    public function setupAssets(): void
    {
        wp_register_script(
            'sitchco/ui-framework',
            $this->url('assets/scripts/main.mjs'),
            ['wp-hooks']
        );
        add_filter('body_class', fn($classes) => array_merge($classes, ['sd-app-loading']));
        add_action('wp_head', function () {
            ?>
            <script>
                window.onload = function() {
                    document.body.classList.remove('sd-app-loading');
                }
            </script>
            <noscript><style>body.sd-app-loading { opacity: 1; } </style></noscript>
            <?php
        });
    }
}
