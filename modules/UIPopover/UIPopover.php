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
            $assets->registerScript(static::hookName(), 'main.js', [UIFramework::hookName()]);
            $assets->registerStyle(static::hookName(), 'main.css');
        });
    }

    public function render(string $triggerContent, string $panelContent, array $options = []): string
    {
        $this->enqueue();
        $panelId = 'sitchco-popover-' . ++static::$counter;

        $triggerDefaults = [
            'type' => 'button',
            'class' => 'sitchco-popover-trigger',
            'aria-expanded' => 'false',
            'aria-controls' => $panelId,
            'aria-haspopup' => 'true',
            'data-popover-trigger' => $panelId,
            'style' => ['anchor-name' => "--$panelId"],
        ];
        $triggerAttrs = ArrayUtil::mergeAttributes($triggerDefaults, $options['trigger_attributes'] ?? []);
        $triggerAttrs = apply_filters(static::hookName('trigger-attributes'), $triggerAttrs, $panelId);

        $panelClasses = ['sitchco-popover'];
        if (!empty($options['arrow'])) {
            $panelClasses[] = 'sitchco-popover--arrow';
        }
        if (!empty($options['backdrop'])) {
            $panelClasses[] = 'sitchco-popover--backdrop';
        }
        $panelDefaults = [
            'id' => $panelId,
            'class' => $panelClasses,
            'popover' => true,
            'style' => ['position-anchor' => "--$panelId"],
        ];
        $panelAttrs = ArrayUtil::mergeAttributes($panelDefaults, $options['panel_attributes'] ?? []);
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
        $this->assets()->enqueueScript(static::hookName());
        $this->assets()->enqueueStyle(static::hookName());
    }
}
