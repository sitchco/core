<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class UIFramework extends Module
{
    const FEATURES = ['loadAssets'];

    const HOOK_SUFFIX = 'ui-framework';

    protected const NO_JS_SCRIPT = "
            document.documentElement.classList.remove('no-js');
            document.documentElement.classList.add('js');
            document.fonts.ready.then(function () {
                document.documentElement.classList.add('fonts-loaded');
            });
        ";

    public function init(): void
    {
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::HOOK_SUFFIX, 'main.mjs', ['wp-hooks']);
            $assets->registerStyle(static::HOOK_SUFFIX, 'main.css');
            add_filter(
                'language_attributes',
                fn($attributes) => !str_contains($attributes, 'class=')
                    ? $attributes . ' class="no-js"'
                    : str_replace('class="', 'class="no-js ', $attributes),
            );
        });
    }

    public function loadAssets(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::HOOK_SUFFIX);
            $assets->enqueueStyle(static::HOOK_SUFFIX);
            $assets->inlineScript(static::HOOK_SUFFIX, static::NO_JS_SCRIPT, 'before');
        });
    }
}
