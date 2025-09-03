<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class UIFramework extends Module
{
    const FEATURES = ['loadAssets'];

    const ASSET_HANDLE = 'ui-framework';

    protected const NO_JS_SCRIPT = "
            document.documentElement.classList.remove('no-js');
            document.documentElement.classList.add('js');
            document.fonts.ready.then(function () {
                document.documentElement.classList.add('fonts-loaded');
            });
        ";

    public function init(): void
    {
        $this->registerAssets(function(ModuleAssets $assets) {
            $assets->registerScript(static::ASSET_HANDLE, 'main.mjs', ['wp-hooks']);
            $assets->registerStyle(static::ASSET_HANDLE, 'main.css');
            add_filter(
                'language_attributes',
                fn($attributes) => !str_contains($attributes, 'class=')
                    ? $attributes . ' class="no-js"'
                    : str_replace('class="', 'class="no-js ', $attributes)
            );
        });
    }

    public function loadAssets(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::ASSET_HANDLE);
            $assets->enqueueStyle(static::ASSET_HANDLE);
            $assets->inlineScript(static::ASSET_HANDLE, static::NO_JS_SCRIPT, 'before');
        });
    }


}
