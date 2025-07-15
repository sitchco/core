<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;

class UIFramework extends Module
{
    const FEATURES = ['loadAssets'];

    const HOOK_SUFFIX = 'ui-framework';
    protected string $noJsScript = "
            document.documentElement.classList.remove('no-js');
            document.documentElement.classList.add('js');
            document.fonts.ready.then(function () {
                document.documentElement.classList.add('fonts-loaded');
            });
        ";

    public function init(): void
    {
        add_action('init', [$this, 'setupAssets'], 5);
    }

    public function setupAssets(): void
    {
        $handle = static::hookName();
        $this->registerScript($handle, $this->scriptUrl('main.mjs'), ['wp-hooks']);
        $this->registerStyle($handle, $this->styleUrl('main.css'));
        add_filter(
            'language_attributes',
            fn($attributes) => !str_contains($attributes, 'class=')
                ? $attributes . ' class="no-js"'
                : str_replace('class="', 'class="no-js ', $attributes)
        );
    }

    public function loadAssets(): void
    {
        add_action('wp_enqueue_scripts', function () {
            $this->enqueueScript(static::hookName());
            $this->enqueueStyle(static::hookName());
            $this->inlineScript(static::hookName(), $this->noJsScript, 'before');
        });
    }
}
