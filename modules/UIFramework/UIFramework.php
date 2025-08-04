<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class UIFramework extends Module
{
    const FEATURES = ['loadAssets'];

    const HOOK_SUFFIX = 'ui-framework';

    protected bool $loadAssets = false;

    protected string $noJsScript = "
            document.documentElement.classList.remove('no-js');
            document.documentElement.classList.add('js');
            document.fonts.ready.then(function () {
                document.documentElement.classList.add('fonts-loaded');
            });
        ";

    public function init(): void
    {
        //add_action('init', [$this, 'setupAssets'], 5);
    }

    public function registerAssets(ModuleAssets $assets): void
    {
        $handle = static::hookName();
        $assets->registerScript($handle, $assets->scriptUrl('main.mjs'), ['wp-hooks']);
        $assets->registerStyle($handle, $assets->styleUrl('main.css'));
        add_filter(
            'language_attributes',
            fn($attributes) => !str_contains($attributes, 'class=')
                ? $attributes . ' class="no-js"'
                : str_replace('class="', 'class="no-js ', $attributes)
        );
    }

    public function enqueueFrontendAssets(ModuleAssets $assets): void
    {
        if ($this->loadAssets) {
            $handle = static::hookName();
            $assets->enqueueScript($handle);
            $assets->enqueueStyle($handle);
            $assets->inlineScript($handle, $this->noJsScript, 'before');
        }
    }

    public function loadAssets(): void
    {
        $this->loadAssets = true;
    }


}
