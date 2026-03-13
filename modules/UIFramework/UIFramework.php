<?php

namespace Sitchco\Modules\UIFramework;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class UIFramework extends Module
{
    public const HOOK_SUFFIX = 'ui-framework';

    const FEATURES = ['loadAssets', 'loadEditorAssets'];

    protected const NO_JS_SCRIPT = "
            document.documentElement.classList.remove('no-js');
            document.documentElement.classList.add('js');
            window.__updateHeaderHeight = function() {
                var h = document.querySelector('header');
                if (h) {
                    document.documentElement.style.setProperty('--dynamic__header-height', h.offsetHeight + 'px');
                }
            };
            document.fonts.ready.then(function () {
                document.documentElement.classList.add('fonts-loaded');
                window.__updateHeaderHeight();
            });
        ";

    /** Sets --dynamic__header-height on <html>; consumed by SiteHeader structural CSS and header-height JS filter. */
    protected const HEADER_HEIGHT_SCRIPT = <<<'HTML'
    <script>window.__updateHeaderHeight();</script>
    HTML;

    protected const EDITOR_FLUSH_SCRIPT = "
            if(window.sitchco?.editorFlush) {
                window.sitchco.editorFlush();
            }
        ";

    public function init(): void
    {
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::hookName('hooks'), 'hooks.js', ['wp-hooks']);
            $assets->registerScript(static::hookName(), 'main.js', [static::hookName('hooks')]);
            $assets->registerScript(static::hookName('editor'), 'editor-ui-main.js', [static::hookName('hooks')]);
            $assets->registerScript(static::hookName('editor-flush'), false);
            $assets->registerStyle(static::hookName(), 'main.css');

            // Backwards-compat aliases for old handle names
            wp_register_script('sitchco/editor-ui-framework', false, [static::hookName('editor')]);
            wp_register_script('sitchco/hooks', false, [static::hookName('hooks')]);
            wp_register_script('sitchco/ui-editor-flush', false, [static::hookName('editor-flush')]);

            add_filter(
                'language_attributes',
                fn($attributes) => !str_contains($attributes, 'class=')
                    ? $attributes . ' class="no-js"'
                    : str_replace('class="', 'class="no-js ', $attributes),
            );
        });
        add_action('sitchco/after_site_header', fn() => print static::HEADER_HEIGHT_SCRIPT);
    }

    public function loadEditorAssets(): void
    {
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName('editor'));
        }, 1);

        // Enqueue flush as the very last editor script.
        // No dependencies needed — WordPress outputs queue items in enqueue order,
        // so PHP_INT_MAX ensures this inline script appears after all other editor scripts.
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName('editor-flush'));
            $assets->inlineScript(static::hookName('editor-flush'), static::EDITOR_FLUSH_SCRIPT);
        }, PHP_INT_MAX);
    }

    public function loadAssets(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName());
            $assets->enqueueStyle(static::hookName());
            $assets->inlineScript(static::hookName(), static::NO_JS_SCRIPT, 'before');
        });
    }
}
