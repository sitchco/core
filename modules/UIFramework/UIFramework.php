<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class UIFramework extends Module
{
    const FEATURES = ['loadAssets', 'loadEditorAssets'];

    const HANDLE = 'ui-framework';

    const HOOKS_HANDLE = 'sitchco/hooks';
    const EDITOR_HANDLE = 'editor-ui-framework';
    const EDITOR_FLUSH_HANDLE = 'ui-editor-flush';

    protected const NO_JS_SCRIPT = "
            document.documentElement.classList.remove('no-js');
            document.documentElement.classList.add('js');
            document.fonts.ready.then(function () {
                document.documentElement.classList.add('fonts-loaded');
            });
        ";

    protected const EDITOR_FLUSH_SCRIPT = "
            console.log('editor flush');
            if(window.sitchco?.editorFlush) {
                window.sitchco.editorFlush();
            }
        ";

    public function init(): void
    {
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::HOOKS_HANDLE, 'hooks.js', ['wp-hooks']);
            $assets->registerScript(static::HANDLE, 'main.js', [static::HOOKS_HANDLE]);
            $assets->registerScript(static::EDITOR_HANDLE, 'editor-ui-main.js', [static::HOOKS_HANDLE]);
            $assets->registerScript(static::EDITOR_FLUSH_HANDLE, false);
            $assets->registerStyle(static::HANDLE, 'main.css');
            add_filter(
                'language_attributes',
                fn($attributes) => !str_contains($attributes, 'class=')
                    ? $attributes . ' class="no-js"'
                    : str_replace('class="', 'class="no-js ', $attributes),
            );
        });
    }

    public function loadEditorAssets(): void
    {
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::EDITOR_HANDLE);
        }, 1);

        // Enqueue flush as the very last editor script.
        // No dependencies needed — WordPress outputs queue items in enqueue order,
        // so PHP_INT_MAX ensures this inline script appears after all other editor scripts.
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::EDITOR_FLUSH_HANDLE);
            $assets->inlineScript(static::EDITOR_FLUSH_HANDLE, static::EDITOR_FLUSH_SCRIPT);
        }, PHP_INT_MAX);
    }

    public function loadAssets(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::HANDLE);
            $assets->enqueueStyle(static::HANDLE);
            $assets->inlineScript(static::HANDLE, static::NO_JS_SCRIPT, 'before');
        });
    }
}
