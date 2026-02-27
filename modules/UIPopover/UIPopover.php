<?php

namespace Sitchco\Modules\UIPopover;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\TimberModule;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\TimberUtil;

class UIPopover extends Module
{
    public const DEPENDENCIES = [TimberModule::class, UIFramework::class];

    const HOOK_SUFFIX = 'ui-popover';

    protected static int $counter = 0;

    public function init(): void
    {
        add_filter('timber/locations', function ($paths) {
            $paths[] = [__DIR__ . '/templates'];
            return $paths;
        });
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::HOOK_SUFFIX, 'main.js', ['sitchco/ui-framework']);
            $assets->registerStyle(static::HOOK_SUFFIX, 'main.css');
        });
    }

    public function render(string $triggerContent, string $panelContent, array $options = []): string
    {
        $this->enqueue();
        $panelId = 'sitchco-popover-' . ++static::$counter;

        $triggerDefaults = [
            'class' => 'sitchco-popover-trigger',
            'aria-expanded' => 'false',
            'aria-controls' => $panelId,
            'aria-haspopup' => 'dialog',
            'data-popover-trigger' => $panelId,
            'style' => ['anchor-name' => "--$panelId"],
        ];
        $triggerAttrs = ArrayUtil::mergeRecursiveDistinct($triggerDefaults, $options['trigger_attributes'] ?? []);
        $triggerAttrs = apply_filters(static::hookName('trigger-attributes'), $triggerAttrs, $panelId);

        $panelDefaults = [
            'id' => $panelId,
            'class' => 'sitchco-popover',
            'popover' => true,
            'style' => ['position-anchor' => "--$panelId"],
        ];
        $panelAttrs = ArrayUtil::mergeRecursiveDistinct($panelDefaults, $options['panel_attributes'] ?? []);
        $panelAttrs = apply_filters(static::hookName('panel-attributes'), $panelAttrs, $panelId);

        $context = [
            'trigger_attributes' => ArrayUtil::toAttributes($triggerAttrs),
            'trigger_content' => $triggerContent,
            'panel_attributes' => ArrayUtil::toAttributes($panelAttrs),
            'panel_content' => $panelContent,
        ];

        return TimberUtil::compileWithContext('popover.twig', $context);
    }

    public function enqueue(): void
    {
        $this->assets()->enqueueScript(static::HOOK_SUFFIX);
        $this->assets()->enqueueStyle(static::HOOK_SUFFIX);
    }
}
