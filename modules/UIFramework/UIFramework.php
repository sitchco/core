<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;

class UIFramework extends Module
{
    const FEATURES = [
        'loadAssets',
    ];

    const HOOK_SUFFIX = 'ui-framework';

    public function init(): void
    {
        add_action('init', [$this, 'setupAssets'], 5);
    }

    public function setupAssets(): void
    {
        wp_register_script_module(
            'vite-client',
            SITCHCO_DEV_SERVER_URL . '/@vite/client',
            [],
            null
        );

        $this->registerScript(
            static::hookName(),
            $this->scriptUrl('main.mjs'),
            ['vite-client', 'wp-hooks']
        );
        wp_register_style(
            static::hookName(),
            $this->styleUrl('main.css'),
        );

        add_filter('body_class', fn($classes) => array_merge($classes, ['sitchco-app-loading']));
        add_action('wp_head', function () {
?>
<script>window.onload = () => document.body.classList.remove('sitchco-app-loading');</script>
<noscript><style>body.sitchco-app-loading { opacity: 1; } </style></noscript>
<?php
        });
    }

    public function loadAssets(): void
    {
        add_action('wp_enqueue_scripts', function () {
            if ($this->isDevServer()) {
                wp_enqueue_script_module('vite-client');
            }
            $this->enqueueScript(static::hookName());
            wp_enqueue_style(static::hookName());
        });
    }
}
